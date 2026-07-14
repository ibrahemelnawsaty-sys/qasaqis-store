<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Baseline store settings (CMS-managed, constitution 0.8). Manual payment methods
 * (InstaPay / Vodafone Cash / COD) are the always-on default path; online payment
 * is OFF until a gateway key exists (doc 04 §5.1). NO secret/API key is stored here
 * (constitution 4.3) — those live in .env / encrypted settings added later.
 *
 * Boolean values are stored as '1' / '0' strings with type=boolean so the reader
 * can cast them consistently.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // --- General store identity -------------------------------------
            ['general', 'site_name', 'قصص أطفال', 'string'],
            ['general', 'site_tagline', 'كتب أطفال منسّقة تربّي وتُمتع', 'string'],
            ['general', 'currency', 'EGP', 'string'],

            // --- Contact ----------------------------------------------------
            // Placeholder WhatsApp number (E.164, no leading +). NOT a real line —
            // the admin sets the store's actual number from the panel.
            ['contact', 'whatsapp_number', '201000000000', 'string'],
            ['contact', 'contact_email', 'info@qasaqis.store', 'string'],

            // --- Payment methods (doc 04 §5.1) ------------------------------
            // Online gateway stays disabled until an API key is configured.
            ['payment', 'online_payment_enabled', '0', 'boolean'],
            ['payment', 'manual_instapay_enabled', '1', 'boolean'],
            ['payment', 'manual_vodafone_enabled', '1', 'boolean'],
            ['payment', 'manual_bank_enabled', '0', 'boolean'],
            ['payment', 'cod_enabled', '1', 'boolean'],
        ];

        foreach ($settings as [$group, $key, $value, $type]) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'group' => $group,
                    'value' => $value,
                    'type' => $type,
                    'is_encrypted' => false,
                    'autoload' => true,
                ],
            );
        }
    }
}
