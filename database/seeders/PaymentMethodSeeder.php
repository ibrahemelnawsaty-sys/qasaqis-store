<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/**
 * Seeds payment_methods with the EXACT codes the orders.payment_method enum and
 * PlaceOrderAction expect: cod, instapay, vodafone_cash, bank_transfer,
 * online_gateway (see the create_orders_table migration enum). The `type` column
 * drives the checkout status mapping in PlaceOrderAction::statusFor():
 *   - cash_on_delivery -> confirmed / unpaid (COD)
 *   - manual_transfer  -> pending / pending_review (customer uploads proof)
 *   - online_gateway   -> pending / unpaid (gateway initiated after commit)
 *
 * Enabled by default: COD + the three manual transfers (constitution 0.6). The
 * online gateway is seeded DISABLED — it is only OFFERED once a real API key is
 * configured (doc 04 §5.1 / PaymentMethodResolver::isOnlineEnabled), so a disabled
 * row here keeps the whole flow working without one. requires_proof=true for the
 * manual transfers (a receipt upload is required to confirm the order).
 *
 * NO secrets or real account numbers are stored here (constitution 4.3):
 * account_details/instructions stay null for the admin to fill from the panel
 * before going live — we never render fabricated wallet/IBAN numbers to a buyer.
 *
 * Idempotent (updateOrCreate on the unique `code`) — safe to re-run; never
 * truncates (constitution 3.3).
 */
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        // [code, name(ar, CMS-editable), type, is_enabled, requires_proof, sort_order]
        $methods = [
            ['cod', 'الدفع عند الاستلام', 'cash_on_delivery', true, false, 1],
            ['instapay', 'إنستاباي', 'manual_transfer', true, true, 2],
            ['vodafone_cash', 'فودافون كاش', 'manual_transfer', true, true, 3],
            ['bank_transfer', 'تحويل بنكي', 'manual_transfer', true, true, 4],
            ['online_gateway', 'الدفع أونلاين بالبطاقة/المحفظة', 'online_gateway', false, false, 5],
        ];

        foreach ($methods as [$code, $name, $type, $isEnabled, $requiresProof, $sortOrder]) {
            PaymentMethod::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'is_enabled' => $isEnabled,
                    'requires_proof' => $requiresProof,
                    'sort_order' => $sortOrder,
                    // account_details/instructions intentionally left null — the
                    // admin adds the real, non-secret transfer details later.
                ],
            );
        }
    }
}
