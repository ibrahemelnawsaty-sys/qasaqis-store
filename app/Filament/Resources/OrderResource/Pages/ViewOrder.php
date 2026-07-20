<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Services\Notifications\OrderNotifier;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Read-only order view carrying the three gated mutation actions for the
 * «الطلبات والدفع» group. Every action is enforced server-side twice: an
 * ->visible() gate that also hides it, AND an abort_unless() re-check inside the
 * closure (constitution 4.4 / anti-pattern 13 — hiding the button is never the
 * control).
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->reviewProofAction(),
            $this->updateStatusAction(),
            $this->updateShippingAction(),
            $this->refundAction(),
        ];
    }

    /**
     * تسجيل مرتجع (جزئي أو كلّي) على الطلب (م٤أ). صلاحية: orders.refund.
     * يُخزَّن المبلغ وتاريخه فيُخصم من الإيراد المحقّق في القسم المالي. المرتجع
     * الكلّي يُنقل الحالة إلى refunded (تُستبعد كليًّا)؛ الجزئي يُبقيها محقّقة.
     */
    private function refundAction(): Action
    {
        return Action::make('refund')
            ->label('تسجيل مرتجع')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->can('orders.refund') === true)
            ->fillForm(fn (): array => [
                'refunded_amount' => $this->record->refunded_amount,
            ])
            ->form([
                TextInput::make('refunded_amount')
                    ->label('المبلغ المُرتجَع (ج.م)')
                    ->helperText('لا يتجاوز إجمالي الطلب. المساوي للإجمالي يُعدّ مرتجعًا كلّيًا.')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    // سقف خادميّ لا واجهيّ فقط: لا يتجاوز المرتجع إجمالي الطلب (4.1).
                    ->maxValue(fn (): string => (string) $this->record->grand_total),
                Textarea::make('refund_note')
                    ->label('سبب المرتجع')
                    ->maxLength(300),
            ])
            ->requiresConfirmation()
            ->action(function (array $data): void {
                abort_unless(auth()->user()?->can('orders.refund') === true, 403);

                $amount = (string) ($data['refunded_amount'] ?? '0');

                // تحقّق خادميّ من السقف بحساب Money — لا نثق بواجهة العميل (4.1).
                if (Money::gte($amount, '0.01') === false
                    || Money::gte((string) $this->record->grand_total, $amount) === false) {
                    Notification::make()->title('مبلغ المرتجع غير صالح')->danger()->send();

                    return;
                }

                $isFull = Money::gte($amount, (string) $this->record->grand_total);

                $fields = [
                    'refunded_amount' => $amount,
                    'refunded_at' => now(),
                    'admin_note' => trim((string) ($this->record->admin_note.' | مرتجع: '.($data['refund_note'] ?? ''))),
                ];
                // مرتجع كلّي ⇒ الحالة refunded (تُستبعد من الإيراد كليًّا). الجزئي
                // يُبقي الحالة كما هي فيظل محقّقًا ويُخصم منه المبلغ في التقارير.
                if ($isFull) {
                    $fields['status'] = 'refunded';
                }

                $this->record->forceFill($fields)->save();

                // تسجيل عملية حسّاسة للمراجعة (4.7).
                Log::info('orders.refunded', [
                    'order_id' => $this->record->id,
                    'amount' => $amount,
                    'full' => $isFull,
                    'actor_id' => auth()->id(),
                ]);

                Notification::make()->title('تم تسجيل المرتجع')->success()->send();
            });
    }

    /**
     * Manual-payment proof review (docs/04 §6). Shows the awaiting proof (rendered
     * as a signed link in the infolist above) and confirms/rejects it, updating the
     * proof, the order, and the linked payment atomically, and stamping confirmed_by.
     * Permission: payment_proof.review.
     */
    private function reviewProofAction(): Action
    {
        return Action::make('reviewProof')
            ->label('مراجعة إثبات الدفع')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('warning')
            ->visible(fn (): bool => auth()->user()?->can('payment_proof.review') === true
                && $this->pendingProof() !== null)
            ->modalHeading('مراجعة إثبات الدفع اليدوي')
            ->modalDescription(fn (): string|Htmlable => 'راجع صورة الإثبات المرفقة أعلى الصفحة قبل اتخاذ القرار.')
            ->form([
                Radio::make('decision')
                    ->label('القرار')
                    ->options([
                        'approve' => 'قبول الإثبات (تأكيد الدفع)',
                        'reject' => 'رفض الإثبات',
                    ])
                    ->required()
                    ->live(),
                Textarea::make('review_note')
                    ->label('سبب الرفض')
                    ->helperText('عند الرفض يُرسَل هذا النص للعميل عبر البريد — اكتب سببًا مناسبًا له.')
                    ->maxLength(300)
                    ->required(fn (callable $get): bool => $get('decision') === 'reject'),
                // رسوم المعالجة (م٤ب): اختيارية، تظهر عند القبول ولمن يملك الصلاحية
                // المالية فقط (حقل مالي سرّي). فارغ = لا رسوم مُدخلة (NULL لا صفر).
                TextInput::make('fee_amount')
                    ->label('رسوم المعالجة (ج.م)')
                    ->helperText('ما تقتطعه البوابة/التحصيل — لحساب صافي الربح بعد الرسوم.')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (callable $get): bool => $get('decision') === 'approve'
                        && auth()->user()?->can('orders.view_financials') === true),
            ])
            ->action(function (array $data): void {
                // Server-side re-check — the ->visible() gate is UI only.
                abort_unless(auth()->user()?->can('payment_proof.review') === true, 403);

                $proof = $this->pendingProof();

                if ($proof === null) {
                    Notification::make()
                        ->title('لا يوجد إثبات قيد المراجعة')
                        ->warning()
                        ->send();

                    return;
                }

                $order = $this->record;
                $approved = $data['decision'] === 'approve';
                $reviewerId = auth()->id();

                // Single transaction across proof + payment + order (constitution 3.5).
                DB::transaction(function () use ($proof, $order, $approved, $reviewerId, $data): void {
                    $proof->forceFill([
                        'review_status' => $approved ? 'approved' : 'rejected',
                        'reviewed_by' => $reviewerId,
                        'reviewed_at' => now(),
                        'review_note' => $data['review_note'] ?? null,
                    ])->save();

                    // Update the linked payment row when present (payment_id nullable).
                    if ($proof->payment_id !== null && $proof->payment instanceof Payment) {
                        $paymentFields = [
                            'status' => $approved ? 'completed' : 'failed',
                            'paid_at' => $approved ? now() : null,
                        ];
                        // رسوم المعالجة تُكتب فقط عند القبول ولمن يملك الصلاحية
                        // المالية (تحقّق خادميّ لا واجهيّ فقط، 4.1/4.4). فارغ ⇒ NULL.
                        if ($approved && auth()->user()?->can('orders.view_financials') === true) {
                            $fee = $data['fee_amount'] ?? null;
                            $paymentFields['fee_amount'] = ($fee === null || $fee === '') ? null : (string) $fee;
                        }

                        $proof->payment->forceFill($paymentFields)->save();
                    }

                    // Approve → paid + processing + confirmed_by (docs/04 §5.4/§6.2).
                    // Reject → failed + back to pending so the customer can re-upload
                    // (orders.status enum has no dedicated "rejected" value).
                    $order->forceFill($approved ? [
                        'payment_status' => 'paid',
                        'status' => 'processing',
                        'confirmed_by' => $reviewerId,
                    ] : [
                        'payment_status' => 'failed',
                        'status' => 'pending',
                    ])->save();
                });

                // إشعار العميل بالنتيجة — بعد الـ commit (M4). ShouldQueue.
                if ($approved) {
                    app(OrderNotifier::class)->paymentApproved($order);
                } else {
                    app(OrderNotifier::class)->paymentRejected($order, $data['review_note'] ?? null);
                }

                // Audit trail for a sensitive operation (constitution 4.7 / docs 6.2).
                Log::info('payment_proof.reviewed', [
                    'order_id' => $order->id,
                    'proof_id' => $proof->id,
                    'decision' => $data['decision'],
                    'reviewer_id' => $reviewerId,
                ]);

                Notification::make()
                    ->title($approved ? 'تم قبول الإثبات وتأكيد الدفع' : 'تم رفض الإثبات')
                    ->{$approved ? 'success' : 'danger'}()
                    ->send();
            });
    }

    /**
     * Change the order lifecycle status. Permission: orders.update_status.
     */
    private function updateStatusAction(): Action
    {
        return Action::make('updateStatus')
            ->label('تحديث حالة الطلب')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->visible(fn (): bool => auth()->user()?->can('orders.update_status') === true)
            ->fillForm(fn (): array => ['status' => $this->record->status])
            ->form([
                Select::make('status')
                    ->label('الحالة الجديدة')
                    ->options(OrderResource::STATUS_LABELS)
                    ->required(),
            ])
            ->action(function (array $data): void {
                abort_unless(auth()->user()?->can('orders.update_status') === true, 403);

                // الحالات النهائية المُسترجَعة نهائية: لا يُعاد تفعيلها (M2). إعادة
                // التفعيل لا تُعيد خصم المخزون، فتُعرض نسخ متاحة أكثر من الحقيقة
                // (بيع زائد). لإعادة البيع يُنشأ طلب جديد.
                $current = $this->record->status;

                if (in_array($current, ['cancelled', 'refused', 'refunded'], true)
                    && $data['status'] !== $current) {
                    Notification::make()
                        ->title('لا يمكن تغيير حالة طلب نهائي (ملغى/مرفوض/مسترجَع)')
                        ->body('أنشئ طلبًا جديدًا بدل إعادة تفعيل طلب انتهى.')
                        ->danger()
                        ->send();

                    return;
                }

                // معاملة تجعل تغيير الحالة + استرجاع المخزون (عبر OrderObserver
                // عند الإلغاء/الرفض/الاسترداد) ذرّيين: فشل الاسترجاع (deadlock)
                // يُرجع الحالة أيضًا. إعادة المحاولة 3 مرات على تعارض القفل.
                DB::transaction(function () use ($data): void {
                    $this->record->forceFill(['status' => $data['status']])->save();
                }, 3);

                Log::info('orders.status_updated', [
                    'order_id' => $this->record->id,
                    'status' => $data['status'],
                    'actor_id' => auth()->id(),
                ]);

                Notification::make()->title('تم تحديث حالة الطلب')->success()->send();
            });
    }

    /**
     * Set shipping company + tracking number. Permission: orders.ship.
     */
    private function updateShippingAction(): Action
    {
        return Action::make('updateShipping')
            ->label('بيانات الشحن')
            ->icon('heroicon-o-truck')
            ->color('primary')
            ->visible(fn (): bool => auth()->user()?->can('orders.ship') === true)
            // لا نُغذّي carrier_cost في حالة النموذج إلا لمن يملك الصلاحية المالية:
            // ->visible يُخفي الحقل من العرض فقط، بينما fillForm يبثّ القيمة في
            // mountedActionsData (خاصية Livewire عامة تُرسَل للمتصفح)، فيتسرّب رغم
            // إخفاء الحقل. البوابة على المصدر تمنع البثّ من أصله (الدستور 4.4).
            ->fillForm(fn (): array => [
                'shipping_company' => $this->record->shipping_company,
                'tracking_number' => $this->record->tracking_number,
            ] + (auth()->user()?->can('orders.view_financials') === true
                ? ['carrier_cost' => $this->record->carrier_cost]
                : []))
            ->form([
                TextInput::make('shipping_company')->label('شركة الشحن')->maxLength(50),
                TextInput::make('tracking_number')->label('رقم التتبّع')->maxLength(80),
                // تكلفة الشحن للشركة (م٣): حقل ماليّ سرّي، مرئي فقط لمن يملك
                // orders.view_financials (لا orders.ship وحدها). فارغ = لم تُدخَل بعد.
                TextInput::make('carrier_cost')
                    ->label('تكلفة الشحن المدفوعة للشركة (ج.م)')
                    ->helperText('ما يُدفع لشركة الشحن — لحساب هامش الشحن. اتركيه فارغًا حتى تصل الفاتورة.')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (): bool => auth()->user()?->can('orders.view_financials') === true),
            ])
            ->action(function (array $data): void {
                abort_unless(auth()->user()?->can('orders.ship') === true, 403);

                $previousTracking = $this->record->tracking_number;

                $fields = [
                    'shipping_company' => $data['shipping_company'] ?? null,
                    'tracking_number' => $data['tracking_number'] ?? null,
                ];

                // تحقّق خادميّ: التكلفة تُكتب فقط إن كان الفاعل يملك الصلاحية المالية،
                // فلا يحقنها من لا يراها عبر طلب مُلفَّق (الدستور 4.1/4.4). القيمة
                // الفارغة تبقى NULL (لم تُدخَل) لا صفرًا.
                if (auth()->user()?->can('orders.view_financials') === true) {
                    $carrier = $data['carrier_cost'] ?? null;
                    $fields['carrier_cost'] = ($carrier === null || $carrier === '')
                        ? null
                        : (string) $carrier;
                }

                $this->record->forceFill($fields)->save();

                // إشعار العميل بالشحن عند تعيين/تغيير رقم تتبّع فعلي فقط (M4) —
                // كي لا يتكرّر الإشعار عند أي حفظ لبيانات الشحن.
                if (filled($this->record->tracking_number)
                    && $this->record->tracking_number !== $previousTracking) {
                    app(OrderNotifier::class)->orderShipped($this->record);
                }

                Log::info('orders.shipping_updated', [
                    'order_id' => $this->record->id,
                    'actor_id' => auth()->id(),
                ]);

                Notification::make()->title('تم تحديث بيانات الشحن')->success()->send();
            });
    }

    private function pendingProof(): ?PaymentProof
    {
        return $this->record->paymentProofs()
            ->where('review_status', 'pending_review')
            ->latest()
            ->first();
    }
}
