<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * قائمة التجهيز (Pick List) — صفحة طباعة واحدة تُغني عن فتح كل طلب على حدة.
 *
 * تُفتح من الإجراء الجماعي «قائمة التجهيز» في جدول الطلبات، فتصل معرّفات الطلبات
 * المُختارة في ‎?orders=1,2,3‎. تُخرج قسمين:
 *   ١) ملخّص السحب: كم نسخة من كل عنوان تُخرَج من المخزن (تجميع عبر كل الطلبات).
 *   ٢) بوليصة تعبئة لكل طلب، كل واحدة على صفحة مستقلة عند الطباعة.
 *
 * الصلاحية: orders.view — نفس صلاحية عرض الطلبات، لأن الصفحة لا تكشف أكثر مما
 * يكشفه مورد الطلبات. تُفحص خادميًا في canAccess() (ينفّذها Filament تلقائيًا عبر
 * mountCanAuthorizeAccess/hydrateCanAuthorizeAccess) وتُعاد صراحةً في mount()
 * (بند 4.4 / ممنوع 13). لم تُخترع صلاحية جديدة (بند 1.1).
 *
 * النطاق: كل قراءة تمرّ بـ OrderResource::getEloquentQuery() لا بالموديل مباشرة،
 * فأي تضييق نطاق يُضاف للمورد مستقبلًا (مثل تقييد دور الدعم) يسري هنا تلقائيًا.
 *
 * لا مكتبات: التخطيط والطباعة بـ CSS خالص داخل القالب (@media print)، والزر
 * الوحيد ينادي window.print() عبر Alpine المتاح أصلًا في اللوحة.
 */
class PickList extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = AdminPanelProvider::GROUP_ORDERS_PAYMENTS;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'قائمة التجهيز';

    protected static ?string $title = 'قائمة التجهيز';

    protected static string $view = 'filament.pages.pick-list';

    /**
     * سقف الطلبات في القائمة الواحدة. أقل بكثير من سقف التصدير: هذه صفحة تُطبع
     * ورقيًا (بوليصة لكل طلب)، وما فوق ذلك يعني مئات الأوراق في أمر طباعة واحد.
     */
    public const MAX_ORDERS = 200;

    /**
     * معرّفات الطلبات المطلوبة بعد التحقق. عامّة كي تبقى عبر تحديثات Livewire.
     *
     * العبث بها من العميل لا يوسّع الوصول: كل قراءة تمرّ بـ
     * OrderResource::getEloquentQuery() والصفحة نفسها محروسة بـ orders.view عند
     * كل hydrate.
     *
     * @var list<int>
     */
    public array $orderIds = [];

    /** عدد المعرّفات الصالحة قبل قصّها على السقف — لتحذير المستخدم عند القصّ. */
    public int $requestedCount = 0;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('orders.view');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $valid = self::parseIds((string) request()->query('orders', ''));

        $this->requestedCount = count($valid);
        $this->orderIds = array_slice($valid, 0, self::MAX_ORDERS);
    }

    /**
     * تحقّق بقائمة بيضاء من مدخل خارجي (بند 4.1): أرقام صحيحة موجبة فقط، بلا
     * تكرار. أي جزء آخر يُهمَل بصمت بدل الوثوق به.
     *
     * @return list<int>
     */
    public static function parseIds(string $raw): array
    {
        $ids = [];

        foreach (explode(',', $raw) as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '' || ! ctype_digit($chunk)) {
                continue;
            }

            $id = (int) $chunk;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * البيانات المُمرَّرة للقالب. تُبنى مرة واحدة لكل عرض فلا يتكرر الاستعلام بين
     * الملخّص والبوالص.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $orders = $this->orders();

        return [
            'orders' => $orders,
            'summary' => $this->summarise($orders),
            'labels' => self::labels(),
            'truncated' => $this->requestedCount > count($this->orderIds),
        ];
    }

    /**
     * الطلبات المطلوبة مع بنودها والكتاب المرتبط (للـ SKU). التحميل المسبق
     * يمنع N+1 عبر البوالص كلها (بند 2.5).
     *
     * @return EloquentCollection<int, Order>
     */
    private function orders(): EloquentCollection
    {
        if ($this->orderIds === []) {
            // بلا اختيار: مجموعة فارغة دون أي استعلام.
            /** @var EloquentCollection<int, Order> */
            return new EloquentCollection;
        }

        /** @var EloquentCollection<int, Order> */
        return OrderResource::getEloquentQuery()
            ->whereKey($this->orderIds)
            ->with([
                'items' => fn ($query) => $query->orderBy('id'),
                'items.book:id,sku',
            ])
            ->orderBy('order_number')
            ->get();
    }

    /**
     * تجميع الكميات حسب الكتاب عبر كل الطلبات المُختارة.
     *
     * مفتاح التجميع book_id حين يوجد؛ وإلا عنوان اللقطة — لأن book_id يصبح NULL
     * إذا حُذف الكتاب لاحقًا (nullOnDelete في هجرة order_items)، وبدون هذا الرجوع
     * الخلفي تندمج كل الكتب المحذوفة في صف واحد.
     *
     * @param  EloquentCollection<int, Order>  $orders
     * @return list<array{title: string, sku: string, quantity: int, orders_count: int}>
     */
    private function summarise(EloquentCollection $orders): array
    {
        $grouped = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $key = $item->book_id !== null ? 'b'.$item->book_id : 't'.$item->book_title;

                if (! isset($grouped[$key])) {
                    $grouped[$key] = [
                        'title' => (string) $item->book_title,
                        'sku' => (string) ($item->book?->sku ?? ''),
                        'quantity' => 0,
                        'order_ids' => [],
                    ];
                }

                $grouped[$key]['quantity'] += (int) $item->quantity;
                $grouped[$key]['order_ids'][$order->getKey()] = true;
            }
        }

        $rows = [];

        foreach ($grouped as $row) {
            $rows[] = [
                'title' => $row['title'],
                'sku' => $row['sku'],
                'quantity' => $row['quantity'],
                'orders_count' => count($row['order_ids']),
            ];
        }

        // الأكثر عددًا أولًا: المالك يسحب الأكوام الكبيرة من الرف أولًا.
        usort($rows, fn (array $a, array $b): int => $b['quantity'] <=> $a['quantity']);

        return $rows;
    }

    /**
     * كل النصوص الظاهرة في القالب. تُعرَّف هنا لا في Blade على نمط المستودع
     * القائم (مثل WhyItemResource::iconOptions() التي يقرأها قالب why-icon)، فيبقى
     * القالب بلا نصوص مثبّتة وتبقى التسميات العربية في PHP كبقية موارد Filament.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'empty' => 'لم تُحدَّد طلبات. افتحي صفحة «الطلبات»، حدّدي الطلبات المطلوب تجهيزها، ثم اختاري «قائمة التجهيز» من الإجراءات الجماعية.',
            'truncated' => 'عُرض أول '.self::MAX_ORDERS.' طلب فقط من الطلبات المحدَّدة.',
            'print' => 'طباعة',
            'summary_heading' => 'ملخّص السحب من المخزن',
            'summary_hint' => 'إجمالي النسخ المطلوب إخراجها من المخزن لكل عنوان.',
            'col_sku' => 'الكود',
            'col_book' => 'الكتاب',
            'col_quantity' => 'عدد النسخ',
            'col_orders' => 'عدد الطلبات',
            'total_copies' => 'إجمالي النسخ',
            'total_titles' => 'عدد العناوين',
            'total_orders' => 'عدد الطلبات',
            'slips_heading' => 'بوالص التعبئة',
            'slip_order' => 'طلب رقم',
            'slip_date' => 'التاريخ',
            'slip_customer' => 'العميل',
            'slip_phone' => 'الهاتف',
            'slip_phone_alt' => 'هاتف بديل',
            'slip_address' => 'العنوان',
            'slip_address_notes' => 'ملاحظات العنوان',
            'slip_shipping' => 'الشحن',
            'slip_payment' => 'الدفع',
            'slip_collect' => 'المبلغ المطلوب تحصيله',
            'slip_total' => 'إجمالي الطلب',
            'slip_note' => 'ملاحظة العميل',
            'slip_items' => 'البنود',
            'slip_packed' => 'تم',
            'slip_no_items' => 'لا بنود في هذا الطلب.',
            'dash' => '—',
        ];
    }

    /**
     * المبلغ المطلوب تحصيله عند التسليم: طلبات الدفع عند الاستلام غير المدفوعة
     * فقط. القيم مأخوذة من enum الهجرة حرفيًا (cod / paid).
     */
    public static function amountToCollect(Order $order): ?string
    {
        if ($order->payment_method !== 'cod' || $order->payment_status === 'paid') {
            return null;
        }

        return (string) $order->grand_total;
    }

    /** وصف طريقة الدفع وحالته بالعربية من خرائط المورد (مصدر واحد للترجمة). */
    public static function paymentLine(Order $order): string
    {
        $method = OrderResource::PAYMENT_METHOD_LABELS[$order->payment_method] ?? (string) $order->payment_method;
        $status = OrderResource::PAYMENT_STATUS_LABELS[$order->payment_status] ?? (string) $order->payment_status;

        return $method.' — '.$status;
    }

    /** سطر العنوان المُجمَّع، بلا فواصل يتيمة حين تغيب أجزاء اختيارية. */
    public static function addressLine(Order $order): string
    {
        $parts = array_filter([
            $order->address_line,
            $order->city,
            $order->governorate,
            $order->state_province,
            $order->country_code,
        ], static fn (mixed $part): bool => filled($part));

        return implode('، ', $parts);
    }
}
