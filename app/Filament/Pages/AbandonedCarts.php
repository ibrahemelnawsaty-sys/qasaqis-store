<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Mail\AbandonedCartRecovery;
use App\Models\Coupon;
use App\Models\EmailSuppression;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

/**
 * متابعة «السلات المتروكة» — عملاء **مسجَّلون** أضافوا كتبًا إلى السلة ولم يُكملوا
 * الطلب. سلة العميل المسجَّل تُحفظ خادميًّا (جدول customer_carts) عبر مزامنة خلفيّة
 * من متجر السلة، وتُمسَح عند إتمام الطلب — فبقاؤها بعد مهلة = سلة متروكة. لكل عميل:
 * بياناته والكتب والإجمالي ومنذ متى + أزرار واتساب/إيميل + كود خصم لاستعادته.
 *
 * لماذا المسجَّلون فقط؟ سلة الزائر تعيش في متصفّحه (localStorage) بلا أي بيانات تواصل
 * على الخادم — لا اسم ولا جوّال — فلا يمكن عرضه ولا التواصل معه. أوّل نقطة نملك فيها
 * هويّة العميل هي حسابه المسجَّل، فهي المرحلة الوحيدة القابلة للتنفيذ (قرار المالك).
 *
 * الأمان: العرض خلف orders.view. الأسعار والعناوين تُحسَب من قاعدة البيانات عند العرض
 * (بند 4.1، لا سعر مخزَّن). توليد الكوبون خلف coupons.manage ويُفحَص خادميًّا، ولا
 * يُولَّد إلا لعميلٍ فعلًا ضمن القائمة (لا معرّف عشوائي).
 */
class AbandonedCarts extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ORDERS_PAYMENTS;

    protected static ?int $navigationSort = 16;

    protected static ?string $navigationLabel = 'سلات متروكة';

    protected static ?string $title = 'متابعة السلات المتروكة';

    protected static string $view = 'filament.pages.abandoned-carts';

    public const PER_PAGE = 25;

    /** لا تُعرَض السلة إلا بعد سكونها هذه المدّة (كي لا نُظهر سلّةً قيد التعديل الآن). */
    public const ABANDONED_AFTER_MINUTES = 60;

    /** كود الخصم المُولَّد: نسبة مئوية ومدّة صلاحية. */
    public const DISCOUNT_PERCENT = 10;

    public const COUPON_DAYS = 7;

    /**
     * أكواد الخصم المُولَّدة في هذا العرض: customer_id => code. #[Locked] فلا يحقن
     * العميل أكوادًا وهمية عبر تحديث Livewire — تُضبط خادميًّا فقط.
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $coupons = [];

    /**
     * حالة نافذة «إرسال بريد استعادة»: composeCustomerId (null = مغلقة) و العنوان/النصّ
     * قابلان للتحرير من الأدمن. المستلِم (composeTo/Name) للعرض فقط — يُعاد اشتقاقه من
     * قاعدة البيانات عند الإرسال (#[Locked] فلا يُحقَن مستلِمٌ عبر تحديث Livewire).
     */
    #[Locked]
    public ?int $composeCustomerId = null;

    #[Locked]
    public string $composeTo = '';

    #[Locked]
    public string $composeName = '';

    public string $composeSubject = '';

    public string $composeBody = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('orders.view');
    }

    /**
     * شارة تنقّل بعدد السلات المتروكة — تنبيه بصري للمالك. تُقيَّم في كل صفحة أدمن
     * (شريط التنقّل)، فنلفّها بـ rescue: لو لم تُشغَّل الهجرة بعد على الخادم (الجدول
     * مفقود) تتدهور إلى «بلا شارة» بدل إسقاط لوحة الأدمن كاملةً بـ 500 (يوازي DEPLOYMENT §12).
     */
    public static function getNavigationBadge(): ?string
    {
        return rescue(static function (): ?string {
            $count = static::baseQuery()->count();

            return $count > 0 ? (string) $count : null;
        }, null, report: false);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * السلات المتروكة: عميل مسجَّل (غير محذوف) له سلة محفوظة سكنت أكثر من المهلة.
     * صفٌّ واحد لكل عميل (customer_id فريد في customer_carts).
     */
    protected static function baseQuery(): Builder
    {
        return DB::table('customer_carts')
            ->join('customers', 'customers.id', '=', 'customer_carts.customer_id')
            ->whereNull('customers.deleted_at')
            ->where('customer_carts.updated_at', '<=', now()->subMinutes(self::ABANDONED_AFTER_MINUTES))
            ->select([
                'customer_carts.id as cart_id',
                'customer_carts.items',
                'customer_carts.updated_at',
                'customers.id as customer_id',
                'customers.name',
                'customers.email',
                'customers.phone_normalized',
            ]);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'carts' => $this->carts(),
            'canCoupon' => (bool) auth()->user()?->can('coupons.manage'),
            'canEmail' => (bool) auth()->user()?->can('campaigns.send'),
        ];
    }

    private function carts(): LengthAwarePaginator
    {
        // الأقدم أولًا: الأطول تركًا أولى بالمتابعة.
        $paginator = static::baseQuery()
            ->orderBy('customer_carts.updated_at')
            ->orderBy('customer_carts.id')
            ->paginate(self::PER_PAGE);

        // items لكل صفّ JSON: [{id,qty}]. نجمع كل المعرّفات ونستعلم الكتب مرّةً واحدة
        // (بند 2.5)، والأسعار من قاعدة البيانات لا من العميل (بند 4.1).
        $decoded = [];
        $bookIds = [];
        foreach ($paginator->items() as $row) {
            $items = json_decode((string) $row->items, true);
            $items = is_array($items) ? $items : [];
            $decoded[$row->cart_id] = $items;
            foreach ($items as $item) {
                $bookIds[(int) ($item['id'] ?? 0)] = true;
            }
        }
        unset($bookIds[0]);

        $books = $bookIds === []
            ? collect()
            : DB::table('books')
                ->whereIn('id', array_keys($bookIds))
                ->whereNull('deleted_at')
                ->get(['id', 'title', 'price'])
                ->keyBy('id');

        foreach ($paginator->items() as $row) {
            $lines = [];
            $total = 0.0;
            foreach ($decoded[$row->cart_id] as $item) {
                $book = $books->get((int) ($item['id'] ?? 0));

                if ($book === null) {
                    continue; // كتاب حُذف/غير موجود — يُتجاوز
                }

                $qty = max(1, (int) ($item['qty'] ?? 1));
                $total += (float) $book->price * $qty;
                $lines[] = (object) ['title' => $book->title, 'qty' => $qty];
            }

            $row->book_lines = $lines;
            $row->cart_total = $total;
            $row->age = Carbon::parse($row->updated_at)->diffForHumans(null, true);
        }

        // أخفِ السلال التي لم يبقَ فيها كتاب متاح (حُذفت كل كتبها) — لا شيء للاستعادة،
        // فلا نعرض بطاقة بإجماليّ صفر ولا رسالة تواصل بلا محتوى.
        $paginator->setCollection(
            $paginator->getCollection()->filter(static fn ($row): bool => $row->book_lines !== [])->values()
        );

        return $paginator;
    }

    /**
     * يُولّد كود خصم استعادة لعميلٍ ذي سلة متروكة ويعرضه للمالك ليُرسله. محروس بـ
     * coupons.manage، ولا يُولَّد إلا لعميلٍ فعلًا ضمن القائمة (تحقّق خادميّ من المعرّف).
     */
    public function generateCoupon(int $customerId): void
    {
        abort_unless(auth()->user()?->can('coupons.manage'), 403);

        $row = static::baseQuery()->where('customers.id', $customerId)->first();

        if ($row === null) {
            return; // معرّف خارج القائمة — يُتجاهَل بصمت
        }

        // لا كوبون لسلةٍ لم يبقَ فيها كتاب متاح (حُذفت كل كتبها) — لا شيء للاستعادة.
        $items = json_decode((string) $row->items, true);
        $ids = array_map(static fn ($item): int => (int) ($item['id'] ?? 0), is_array($items) ? $items : []);

        if (! DB::table('books')->whereIn('id', $ids)->whereNull('deleted_at')->exists()) {
            return;
        }

        $code = Coupon::createRecovery(
            'خصم استعادة سلة متروكة لعميل رقم '.$customerId,
            self::DISCOUNT_PERCENT,
            self::COUPON_DAYS,
            'CART',
        );

        if ($code === null) {
            Notification::make()->title('تعذّر توليد كود فريد، حاول مجددًا.')->danger()->send();

            return;
        }

        $this->coupons[$customerId] = $code;

        Notification::make()
            ->title('تم توليد كود الخصم: '.$code)
            ->body('خصم '.self::DISCOUNT_PERCENT.'% صالح '.self::COUPON_DAYS.' أيام. أرسله للعميل عبر واتساب أو الإيميل.')
            ->success()
            ->send();
    }

    /**
     * يفتح نافذة «إرسال بريد استعادة» لعميلٍ ضمن القائمة: يُعبّئ المستلِم والعنوان ونصّ
     * الرسالة (بالإجمالي وكود الخصم إن وُلِّد) ليراجعها الأدمن ثم يرسلها. محروس بـ
     * campaigns.send، ولا يُفتَح إلا لعميلٍ فعلًا ضمن القائمة (تحقّق خادميّ).
     */
    public function composeEmail(int $customerId): void
    {
        abort_unless(auth()->user()?->can('campaigns.send'), 403);

        $row = static::baseQuery()->where('customers.id', $customerId)->first();

        if ($row === null) {
            return; // خارج القائمة — يُتجاهَل بصمت
        }

        if (blank($row->email)) {
            Notification::make()->title('لا يوجد بريد لهذا العميل.')->warning()->send();

            return;
        }

        $this->composeCustomerId = $customerId;
        $this->composeTo = (string) $row->email;
        $this->composeName = (string) $row->name;
        $this->composeSubject = 'سلتك في «قصاقيص أطفال» بانتظارك 🌸';
        $this->composeBody = $this->recoveryMessageFor($row);
    }

    /**
     * يُغلق نافذة إرسال البريد. لا بدّ أن يُعاد ضبط composeCustomerId خادميًّا (وليس عبر
     * $set من المتصفّح) لأنه #[Locked] — و$set على خاصية مقفولة يرفضه Livewire بـ 419.
     */
    public function closeCompose(): void
    {
        $this->composeCustomerId = null;
        $this->composeBody = '';
    }

    /**
     * يُرسل بريد الاستعادة الذي راجعه الأدمن من المتجر بالعلامة (قالب emails.layout)،
     * تزامنيًّا (بلا طابور — يعمل على الاستضافة المشتركة كـInquiryReplied). محروس بـ
     * campaigns.send، ويُعيد اشتقاق المستلِم من قاعدة البيانات (لا يثق بحقول العميل)،
     * ويحترم إلغاء الاشتراك (EmailSuppression) فلا يُراسِل من طلب عدم المراسلة.
     */
    public function sendRecoveryEmail(): void
    {
        abort_unless(auth()->user()?->can('campaigns.send'), 403);

        if ($this->composeCustomerId === null) {
            return;
        }

        $row = static::baseQuery()->where('customers.id', $this->composeCustomerId)->first();

        if ($row === null || blank($row->email)) {
            $this->composeCustomerId = null;

            return;
        }

        $subject = trim($this->composeSubject);
        $body = trim($this->composeBody);

        if ($subject === '' || $body === '') {
            Notification::make()->title('أكمل العنوان ونصّ الرسالة أولًا.')->warning()->send();

            return;
        }

        if (EmailSuppression::query()->where('email', $row->email)->exists()) {
            Notification::make()
                ->title('هذا العميل ألغى الاشتراك من الرسائل — لم يُرسَل شيء.')
                ->danger()
                ->send();

            return;
        }

        try {
            Mail::to($row->email, $row->name)->send(new AbandonedCartRecovery($subject, $body));
        } catch (\Throwable $e) {
            report($e); // فشل SMTP لا يُسقط الصفحة بـ 500
            Notification::make()->title('تعذّر إرسال البريد — حاول لاحقًا.')->danger()->send();

            return;
        }

        $this->composeCustomerId = null; // إغلاق النافذة
        $this->composeBody = '';

        Notification::make()->title('تم إرسال البريد إلى '.$row->email)->success()->send();
    }

    /** نصّ رسالة الاستعادة المُعبّأ: تحيّة + إجماليّ السلة (من قاعدة البيانات، بند 4.1) + كود الخصم إن وُلِّد. */
    private function recoveryMessageFor(object $row): string
    {
        $items = json_decode((string) $row->items, true);
        $qtyById = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $qtyById[(int) ($item['id'] ?? 0)] = max(1, (int) ($item['qty'] ?? 1));
        }
        unset($qtyById[0]);

        $total = 0.0;
        if ($qtyById !== []) {
            $books = DB::table('books')
                ->whereIn('id', array_keys($qtyById))
                ->whereNull('deleted_at')
                ->get(['id', 'price']);
            foreach ($books as $book) {
                $total += (float) $book->price * $qtyById[(int) $book->id];
            }
        }

        $message = 'السلام عليكم '.$row->name.' 🌸'."\n"
            .'لاحظنا أنك أضفتِ كتبًا إلى سلتك في «قصاقيص أطفال» بمبلغ '.number_format($total, 0).' ج.م ولم تُكملي الطلب بعد.'."\n"
            .'يسعدنا مساعدتك في إتمامه!';

        $code = $this->coupons[(int) $row->customer_id] ?? null;
        if ($code !== null) {
            $message .= "\n".'🎁 وهديّة لك: كود خصم '.$code.' — خصم '.self::DISCOUNT_PERCENT.'% صالح '.self::COUPON_DAYS.' أيام.';
        }

        return $message;
    }
}
