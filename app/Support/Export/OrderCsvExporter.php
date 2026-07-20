<?php

declare(strict_types=1);

namespace App\Support\Export;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * تصدير الطلبات إلى CSV يفتحه إكسل العربي مباشرة.
 *
 * الشكل: صف لكل بند طلب (one row per order item) مع تكرار أعمدة الطلب — هو الشكل
 * الوحيد الذي يسمح للمالك بعمل PivotTable على الكتب دون معالجة يدوية. الطلب بلا
 * بنود (حالة حدّية: بنوده حُذفت) يُخرج صفًّا واحدًا بأعمدة بنود فارغة فلا يختفي
 * من التصدير بصمت.
 *
 * الترميز: UTF-8 **مع BOM**. بدون BOM يفتح Excel على ويندوز العربي الملف بترميز
 * النظام (windows-1256) فتظهر العربية طلاسم. ونهايات السطور CRLF لنفس السبب.
 *
 * حقن الصيغ (CSV / Formula Injection): اسم العميل والعنوان والملاحظات نصوص يكتبها
 * العميل. خلية تبدأ بـ = + @ أو تاب/CR يعاملها Excel كصيغة قابلة للتنفيذ، فتُسبَق
 * بعلامة اقتباس مفردة تُبطل التنفيذ ويظل النص مقروءًا (الباب الرابع + المرحلة «هـ»).
 *
 * الاتجاه المعماري: هذا الصنف يقرأ خرائط التسميات العربية من OrderResource لأنها
 * المصدر الوحيد المعتمد لترجمة قيم enum المنسوخة حرفيًا من الهجرة؛ نسخها هنا كان
 * سيخلق مصدرين يتباعدان (بند 1.1). لا يعتمد على Filament فيما عدا ذلك، ويُختبر
 * بمعزل بكتابة إلى أي stream.
 *
 * الأموال: تُكتب كما يعيدها cast decimal:2 نصًّا («200.00») — لا تحويل إلى float
 * ولا تنسيق محلي (بند 3.5).
 */
final class OrderCsvExporter
{
    /** UTF-8 BOM — بدونه يفتح إكسل العربي الملف كطلاسم. */
    public const BOM = "\xEF\xBB\xBF";

    /**
     * سقف الطلبات في التصدير الواحد.
     *
     * ليس تحكّمًا في الصلاحيات بل حماية ذاكرة: Livewire يلتقط ناتج streamDownload
     * كاملًا في مخزن مؤقت ثم يرسله base64 داخل استجابة JSON
     * (Livewire\Features\SupportFileDownloads\SupportFileDownloads::call) — فالتصدير
     * غير المحدود يستهلك ذاكرة الاستضافة المشتركة. 2000 طلب ≈ ملف بضعة ميغابايت.
     */
    public const MAX_ORDERS = 2000;

    private const DELIMITER = ',';

    private const ENCLOSURE = '"';

    /**
     * تعطيل آلية الهروب الخاصة بـ PHP (backslash) والاكتفاء بمضاعفة الاقتباس
     * حسب RFC 4180 — وهو ما يفهمه Excel. تمريرها صراحةً إلزامي: PHP 8.4 يطلق
     * E_DEPRECATED عند حذفها («the $escape parameter must be provided»).
     */
    private const ESCAPE = '';

    /** Excel على ويندوز يتوقع CRLF. */
    private const EOL = "\r\n";

    /** المحارف التي يعاملها Excel كبداية صيغة. */
    private const FORMULA_TRIGGERS = ['=', '+', '@', "\t", "\r"];

    /**
     * ترويسة الملف. الصياغة العربية مطابقة لتسميات أعمدة OrderResource كي يقرأ
     * المالك في الملف ما يراه على الشاشة.
     *
     * @return list<string>
     */
    public function headers(): array
    {
        return [
            'رقم الطلب',
            'تاريخ الإنشاء',
            'حالة الطلب',
            'حالة الدفع',
            'طريقة الدفع',
            'العميل',
            'الهاتف',
            'هاتف بديل',
            'البريد',
            'الدولة',
            'المحافظة',
            'المنطقة / الولاية',
            'المدينة',
            'العنوان',
            'ملاحظات العنوان',
            'شركة الشحن',
            'رقم التتبّع',
            'كود الكوبون',
            'المجموع الفرعي',
            'الخصم',
            'الشحن',
            'الإجمالي',
            'ملاحظة العميل',
            'الكتاب',
            'سعر الوحدة',
            'الكمية',
            'إجمالي البند',
        ];
    }

    /** اسم ملف ASCII بحت — لا لبس في ترميز ترويسة Content-Disposition. */
    public function filename(): string
    {
        return 'orders-'.now()->format('Y-m-d-Hi').'.csv';
    }

    /**
     * يكتب BOM + الترويسة + صفوف الطلبات إلى stream مفتوح، ويعيد عدد الطلبات
     * المكتوبة. الاستقبال iterable كي يعمل مع LazyCollection المقطّعة فلا تُحمَّل
     * كل الطلبات في الذاكرة دفعة واحدة.
     *
     * @param  resource  $handle
     * @param  iterable<int, Order>  $orders
     */
    public function write($handle, iterable $orders): int
    {
        fwrite($handle, self::BOM);
        $this->putRow($handle, $this->headers());

        $written = 0;

        foreach ($orders as $order) {
            foreach ($this->rowsFor($order) as $row) {
                $this->putRow($handle, $row);
            }

            $written++;
        }

        return $written;
    }

    /**
     * صفوف طلب واحد: صف لكل بند، أو صف واحد بأعمدة بنود فارغة إن كان بلا بنود.
     *
     * @return list<list<string>>
     */
    public function rowsFor(Order $order): array
    {
        $orderColumns = $this->orderColumns($order);
        $items = $order->items;

        if ($items->isEmpty()) {
            return [[...$orderColumns, '', '', '', '']];
        }

        $rows = [];

        foreach ($items as $item) {
            $rows[] = [...$orderColumns, ...$this->itemColumns($item)];
        }

        return $rows;
    }

    /**
     * أعمدة الطلب المتكررة على كل بند.
     *
     * @return list<string>
     */
    private function orderColumns(Order $order): array
    {
        return [
            (string) $order->order_number,
            $order->created_at?->format('Y-m-d H:i') ?? '',
            OrderResource::STATUS_LABELS[$order->status] ?? (string) $order->status,
            OrderResource::PAYMENT_STATUS_LABELS[$order->payment_status] ?? (string) $order->payment_status,
            OrderResource::PAYMENT_METHOD_LABELS[$order->payment_method] ?? (string) $order->payment_method,
            (string) $order->customer_name,
            (string) $order->customer_phone,
            (string) ($order->customer_phone_alt ?? ''),
            (string) ($order->customer_email ?? ''),
            (string) ($order->country_code ?? ''),
            (string) ($order->governorate ?? ''),
            (string) ($order->state_province ?? ''),
            (string) ($order->city ?? ''),
            (string) $order->address_line,
            (string) ($order->address_notes ?? ''),
            (string) ($order->shipping_company ?? ''),
            (string) ($order->tracking_number ?? ''),
            (string) ($order->coupon_code ?? ''),
            (string) $order->subtotal,
            (string) $order->discount_total,
            (string) $order->shipping_total,
            (string) $order->grand_total,
            (string) ($order->customer_note ?? ''),
        ];
    }

    /**
     * أعمدة البند. book_title لقطة وقت الطلب (عمود في order_items) فلا تتأثر
     * بتعديل اسم الكتاب لاحقًا ولا بحذفه.
     *
     * @return list<string>
     */
    private function itemColumns(OrderItem $item): array
    {
        return [
            (string) $item->book_title,
            (string) $item->unit_price,
            (string) $item->quantity,
            (string) $item->line_total,
        ];
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $row
     */
    private function putRow($handle, array $row): void
    {
        fputcsv(
            $handle,
            array_map($this->neutraliseFormula(...), $row),
            self::DELIMITER,
            self::ENCLOSURE,
            self::ESCAPE,
            self::EOL,
        );
    }

    /**
     * يبطل تفسير الخلية كصيغة في Excel/LibreOffice بإضافة اقتباس مفرد بادئ.
     *
     * الشرطة «-» تُستثنى إن كانت القيمة رقمية كي لا تتحول الأرقام السالبة إلى نص.
     */
    private function neutraliseFormula(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];

        if (in_array($first, self::FORMULA_TRIGGERS, true)) {
            return "'".$value;
        }

        if ($first === '-' && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }
}
