<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Policies\OrderPolicy;
use App\Support\Order\OrderLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * ‏طلبات العميلة: القائمة (customer.orders.index → GET /account/orders) وصفحة الطلب
 * ‏الواحد (customer.orders.show → GET /account/orders/{order}).
 *
 * ‏التفويض خادمي عند نقطة الفعل نفسها عبر OrderPolicy (الدستور 4.4 / الممنوع 11.13).
 * ‏السياسة تُستدعى مباشرةً لا عبر Gate — السبب موثّق كاملًا في OrderPolicy.
 */
final class OrderHistoryController extends Controller
{
    /** ‏ترقيم إلزامي: سجل طويل على شبكة ضعيفة لا يُحمَّل دفعة واحدة (الدستور 1.6/5.1). */
    private const PER_PAGE = 15;

    /**
     * ‏تقليل البيانات: رقم الطلب والتاريخ والحالة والإجمالي فقط. **بلا** عنوان ولا
     * ‏هاتف ولا ملاحظات — القائمة لا تحتاجها، وما لا يُرسَل لا يُسرَّب.
     *
     * @var list<string>
     */
    private const LIST_COLUMNS = [
        'id',
        'order_number',
        'status',
        'payment_status',
        'grand_total',
        'created_at',
    ];

    public function __construct(private readonly OrderPolicy $policy) {}

    public function index(Request $request): View
    {
        $customer = $this->customer($request);

        return view('customer.orders.index', [
            'customer' => $customer,
            // ‏select قبل withCount عمدًا: العكس يجعل select يمحو عمود العدّ.
            // ‏withCount عدّ فرعي واحد لكل الصفحة — لا N+1 (الدستور 2.5 / الممنوع 7).
            'orders' => Order::query()
                ->where('customer_id', $customer->getKey())
                ->select(self::LIST_COLUMNS)
                ->withCount('items')
                ->latest('created_at')
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $customer = $this->customer($request);

        // ‏الفحص الخادمي الوحيد الحاكم. لا يسبقه أي إخفاء واجهي ولا يليه.
        abort_unless($this->policy->view($customer, $order), 403);

        $order->load('items');

        return view('customer.orders.show', [
            'customer' => $customer,
            'order' => $order,
            // ‏رابط رفع الإثبات يُولَّد **خادميًا** وموقّتًا، ولا يُبنى في القالب:
            // ‏القالب لا يملك قرار الصلاحية. null لغير المنتظِرة للمراجعة.
            'proofUrl' => $order->payment_status === 'pending_review'
                ? OrderLinks::signedPaymentFor($order)
                : null,
        ]);
    }

    /**
     * ‏خط دفاع ثانٍ فقط: البوابة الحقيقية هي middleware الحارس على مجموعة المسارات.
     */
    private function customer(Request $request): Customer
    {
        $customer = $request->user('customer');

        if (! $customer instanceof Customer) {
            abort(403);
        }

        return $customer;
    }
}
