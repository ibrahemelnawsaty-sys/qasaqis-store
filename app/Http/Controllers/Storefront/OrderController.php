<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Order\FindGuestOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentProofRequest;
use App\Http\Requests\TrackOrderRequest;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Notifications\OrderNotifier;
use App\Support\Order\OrderLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Guest-safe order pages. Access is granted only through SIGNED links (the
 * routes carry the `signed` middleware), so an order cannot be enumerated by id.
 * Payment proofs are stored on the PRIVATE `local` disk with a random name and
 * are never served from a public path (constitution 4.5).
 */
class OrderController extends Controller
{
    /** Private disk for uploaded proofs (storage/app/private). */
    private const PROOF_DISK = 'local';

    /**
     * Manual-transfer instructions + proof upload form.
     */
    public function payment(Order $order): View
    {
        $method = PaymentMethod::where('code', $order->payment_method)->first();

        $order->load('paymentProofs');

        return view('orders.payment', [
            'order' => $order,
            'method' => $method,
            'proofUrl' => URL::signedRoute('orders.proof.store', ['order' => $order->id]),
        ]);
    }

    /**
     * Store an uploaded transfer proof and put the order under review.
     */
    public function proofStore(PaymentProofRequest $request, Order $order, OrderNotifier $notifier): RedirectResponse
    {
        $file = $request->file('proof');

        // Random, safe filename — never trust the client's original name.
        $filename = Str::random(40).'.'.$file->extension();
        $path = $file->storeAs("payment-proofs/{$order->id}", $filename, self::PROOF_DISK);

        $payment = $order->payments()
            ->where('status', 'pending_review')
            ->latest()
            ->first();

        $order->paymentProofs()->create([
            'payment_id' => $payment?->id,
            'method_code' => $order->payment_method,
            'file_path' => $path,
            'amount' => $request->validated('amount') ?? $order->grand_total,
            'sender_reference' => $request->validated('sender_reference'),
            // review_status defaults to pending_review.
        ]);

        // Keep the order awaiting review (unpaid until an admin approves).
        if ($order->payment_status !== 'pending_review') {
            $order->update(['payment_status' => 'pending_review']);
        }

        // تنبيه الأدمن أن إثباتًا ينتظر المراجعة (M4) + إيصال للعميلة عن الإثبات
        // الأول فقط (M7) — الرفع المتكرّر لا يُغرِق بريدها برسائل متطابقة.
        $notifier->paymentProofSubmitted(
            $order,
            notifyCustomer: $order->paymentProofs()->count() === 1,
        );

        return redirect()
            ->to(URL::signedRoute('orders.thankyou', ['order' => $order->id]))
            ->with('status', __('payment.proof.uploaded'));
    }

    /**
     * Thank-you / order summary page.
     */
    public function thankyou(Order $order): View
    {
        $order->load('items');

        return view('orders.thankyou', [
            'order' => $order,
        ]);
    }

    /**
     * «تتبّع الطلب» — نموذج عام للضيف (رقم الطلب + الجوال).
     */
    public function trackForm(): View
    {
        return view('orders.track');
    }

    /**
     * يتحقق من المطابقة ويعيد توجيهًا برابط موقّت للصفحة المناسبة. عند الفشل رسالة
     * موحّدة تمنع تعداد الطلبات (لا نعيد إدخال الجوال). المنطق في Action نحيف (2.2).
     */
    public function track(TrackOrderRequest $request, FindGuestOrderAction $finder): RedirectResponse
    {
        $order = $finder->execute(
            (string) $request->validated('order_number'),
            (string) $request->validated('phone'),
        );

        if ($order === null) {
            return redirect()
                ->route('orders.track.show')
                ->withInput($request->only('order_number'))
                ->with('error', __('payment.track.not_found'));
        }

        return redirect()->to(OrderLinks::signedDestinationFor($order));
    }
}
