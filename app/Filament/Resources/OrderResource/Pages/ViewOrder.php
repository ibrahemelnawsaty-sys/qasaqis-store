<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Payment;
use App\Models\PaymentProof;
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
        ];
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
                    ->label('سبب الرفض / ملاحظة')
                    ->maxLength(300)
                    ->required(fn (callable $get): bool => $get('decision') === 'reject'),
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
                        $proof->payment->forceFill([
                            'status' => $approved ? 'completed' : 'failed',
                            'paid_at' => $approved ? now() : null,
                        ])->save();
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
            ->fillForm(fn (): array => [
                'shipping_company' => $this->record->shipping_company,
                'tracking_number' => $this->record->tracking_number,
            ])
            ->form([
                TextInput::make('shipping_company')->label('شركة الشحن')->maxLength(50),
                TextInput::make('tracking_number')->label('رقم التتبّع')->maxLength(80),
            ])
            ->action(function (array $data): void {
                abort_unless(auth()->user()?->can('orders.ship') === true, 403);

                $this->record->forceFill([
                    'shipping_company' => $data['shipping_company'] ?? null,
                    'tracking_number' => $data['tracking_number'] ?? null,
                ])->save();

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
