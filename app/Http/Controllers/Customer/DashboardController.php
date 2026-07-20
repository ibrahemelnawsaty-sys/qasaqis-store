<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * ‏لوحة الحساب (customer.dashboard → GET /account).
 *
 * ‏العدّادات تُحسب **عند العرض** من جدول orders، ولا تُقرأ إطلاقًا من العمودين
 * ‏customers.orders_count / customers.total_spent: لا يوجد في المستودع أي كود يكتبهما
 * ‏(نفس مصير books.avg_rating / reviews_count الراكدَين على 0)، فقراءتهما كذب مؤكد.
 * ‏المصدر الوحيد للحقيقة هو orders.
 */
final class DashboardController extends Controller
{
    /**
     * ‏حالات لا تُحتسب إنفاقًا: مال لم/لن يُقبض. القيم منقولة حرفيًا من enum العمود
     * ‏orders.status في create_orders_table (الدستور 1.1) — لا تُخمَّن.
     *
     * @var list<string>
     */
    private const NON_SPENT_STATUSES = ['cancelled', 'refused', 'refunded'];

    /** ‏بطاقة آخر طلب: أعمدة صريحة فقط — لا عنوان ولا هاتف على اللوحة. */
    private const LAST_ORDER_COLUMNS = [
        'id',
        'order_number',
        'status',
        'payment_status',
        'grand_total',
        'created_at',
    ];

    public function index(Request $request): View
    {
        $customer = $this->customer($request);

        return view('customer.dashboard', [
            'customer' => $customer,
            'ordersCount' => $this->orders($customer)->count(),
            'totalSpent' => $this->totalSpent($customer),
            'lastOrder' => $this->orders($customer)
                ->select(self::LAST_ORDER_COLUMNS)
                ->latest('created_at')
                ->first(),
        ]);
    }

    /**
     * ‏إجمالي المنفَق فعليًا. Money (bcmath) لا float — المبالغ decimal(10,2)
     * ‏والدستور 3.5 يمنع الحساب العائم. SUM على مجموعة فارغة تعيد null → "0.00".
     */
    private function totalSpent(Customer $customer): string
    {
        return Money::normalize(
            $this->orders($customer)
                ->whereNotIn('status', self::NON_SPENT_STATUSES)
                ->sum('grand_total')
        );
    }

    /**
     * ‏نطاق طلبات العميلة. **حصريًا** بـ customer_id — يُمنع منعًا باتًا توسيعه
     * ‏بمطابقة customer_phone: أي شخص يستطيع تقديم طلب برقم غيره، فالمطابقة بالرقم
     * ‏تسرّب طلبات الغير. استعلام جديد في كل نداء (لا clone مشترك الحالة).
     */
    private function orders(Customer $customer): Builder
    {
        return Order::query()->where('customer_id', $customer->getKey());
    }

    /**
     * ‏خط دفاع ثانٍ فقط: البوابة الحقيقية هي middleware الحارس على مجموعة المسارات.
     * ‏لو سقط الربط سهوًا نفشل **مغلقين** بدل عرض الصفحة بعميلة null.
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
