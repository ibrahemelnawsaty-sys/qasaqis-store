<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Coupon;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

/**
 * متابعة الطلبات المتروكة — عملاء بدؤوا طلبًا ولم يُكملوا الدفع/التأكيد (status=pending
 * ودفع unpaid/failed). لكل عميل: بياناته والكتب والإجمالي ومنذ متى + أزرار تواصل
 * واتساب/إيميل، وزرّ توليد كود خصم لاستعادته.
 *
 * لماذا الطلبات لا «السلة»؟ السلة تُخزَّن في الجلسة فقط (لا سجل خادميّ ولا بيانات
 * تواصل قبل الشراء)، وبدء الطلب أوّل نقطة نملك فيها اسم العميل وجواله وإيميله — فهي
 * المرحلة الوحيدة القابلة للتنفيذ (تواصل + خصم).
 *
 * الأمان: عرض القائمة خلف orders.view (كمورد الطلبات، عبر getEloquentQuery فيرث
 * أي تضييق نطاق). توليد الكوبون خلف coupons.manage ويُفحص خادميًّا (بند 4.4)، ولا
 * يُولَّد إلا لطلب فعلًا ضمن القائمة (لا معرّف عشوائي).
 */
class AbandonedOrders extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ORDERS_PAYMENTS;

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'طلبات لم تكتمل';

    protected static ?string $title = 'متابعة الطلبات المتروكة';

    protected static string $view = 'filament.pages.abandoned-orders';

    public const PER_PAGE = 25;

    /** كود الخصم المُولَّد: نسبة مئوية ومدّة صلاحية. */
    public const DISCOUNT_PERCENT = 10;

    public const COUPON_DAYS = 7;

    /**
     * أكواد الخصم المُولَّدة في هذا العرض: order_id => code (لتضمينها في الرسالة).
     * #[Locked] فلا يحقن العميل أكوادًا وهمية عبر تحديث Livewire — تُضبط خادميًّا فقط.
     */
    #[Locked]
    public array $coupons = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('orders.view');
    }

    /** شارة تنقّل بعدد الطلبات المتروكة — تنبيه بصري للمالك. */
    public static function getNavigationBadge(): ?string
    {
        $count = static::baseQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * الطلبات المتروكة: بُدئت (pending) ولم يُكمَل دفعها (unpaid/failed). تمرّ عبر
     * OrderResource::getEloquentQuery() فترث الأرشفة وأيّ تضييق نطاق مستقبلي.
     */
    protected static function baseQuery(): Builder
    {
        return OrderResource::getEloquentQuery()
            ->where('status', 'pending')
            ->whereIn('payment_status', ['unpaid', 'failed']);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'orders' => $this->orders(),
            'canCoupon' => (bool) auth()->user()?->can('coupons.manage'),
        ];
    }

    private function orders(): LengthAwarePaginator
    {
        // الأقدم أولًا: الأطول تركًا أولى بالمتابعة. تحميل البنود مسبقًا (بند 2.5).
        return static::baseQuery()
            ->with(['items:id,order_id,book_title,quantity'])
            ->oldest('created_at')
            ->paginate(self::PER_PAGE);
    }

    /**
     * يُولّد كود خصم استعادة لطلب متروك ويعرضه للمالك ليُرسله للعميل. محروس بـ
     * coupons.manage، ولا يُولَّد إلا لطلب فعلًا ضمن القائمة (تحقّق خادميّ من المعرّف).
     */
    public function generateCoupon(int $orderId): void
    {
        abort_unless(auth()->user()?->can('coupons.manage'), 403);

        $order = static::baseQuery()->whereKey($orderId)->first();

        if ($order === null) {
            return; // معرّف خارج القائمة — يُتجاهَل بصمت
        }

        $description = 'خصم استعادة طلب متروك رقم '.$order->order_number;

        // idempotent: إن سبق توليد كوبون استعادة لهذا الطلب ولا يزال نشطًا وغير
        // مستهلك وغير منتهٍ، نعيد كوده بدل إنشاء ثانٍ (منع تكرار عند تحديث الصفحة).
        $existing = Coupon::query()
            ->where('description', $description)
            ->where('is_active', true)
            ->where('used_count', 0)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->value('code');

        if ($existing !== null) {
            $this->coupons[$orderId] = $existing;
            Notification::make()->title('كود الخصم الحالي لهذا الطلب: '.$existing)->success()->send();

            return;
        }

        $code = $this->createRecoveryCoupon($description);

        if ($code === null) {
            Notification::make()->title('تعذّر توليد كود فريد، حاول مجددًا.')->danger()->send();

            return;
        }

        $this->coupons[$orderId] = $code;

        Notification::make()
            ->title('تم توليد كود الخصم: '.$code)
            ->body('خصم '.self::DISCOUNT_PERCENT.'% صالح '.self::COUPON_DAYS.' أيام. أرسله للعميل عبر واتساب أو الإيميل.')
            ->success()
            ->send();
    }

    /**
     * ينشئ كوبون استعادة بكود فريد. حلقة إعادة محاولة تلتقط تصادم القيد الفريد
     * (سباق TOCTOU نادر) فلا يُسقط الصفحة بـ 500؛ الأخطاء الأخرى تُرمى كما هي.
     */
    private function createRecoveryCoupon(string $description): ?string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = 'BACK'.strtoupper(Str::random(5));

            try {
                Coupon::query()->create([
                    'code' => $code,
                    'description' => $description,
                    'type' => 'percentage',
                    'value' => self::DISCOUNT_PERCENT,
                    'starts_at' => now(),
                    'expires_at' => now()->addDays(self::COUPON_DAYS),
                    'usage_limit' => 1,
                    'usage_limit_per_user' => 1,
                    'applies_to' => 'all',
                    'is_active' => true,
                    'free_shipping' => false,
                ]);

                return $code;
            } catch (QueryException $e) {
                // 23000 = انتهاك قيد سلامة (تصادم unique) → جرّب كودًا آخر. غيره يُرمى.
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        return null;
    }
}
