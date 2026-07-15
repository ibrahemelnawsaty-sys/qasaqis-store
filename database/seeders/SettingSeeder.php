<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Baseline store settings (CMS-managed, constitution 0.8). Every key here is the
 * canonical key the storefront + admin panel agree on; all are NON-secret
 * (constitution 4.3). Payment gateway API keys are NOT here — they live in .env /
 * encrypted settings added elsewhere.
 *
 * Idempotent: updateOrCreate keyed on `key` (unique). Re-running never duplicates
 * and never destroys admin-edited rows outside this list (constitution 3.3).
 *
 * Social links ship EMPTY on purpose ("ready to fill" — the admin pastes each
 * profile URL from the panel). Manual payment methods (InstaPay / Vodafone Cash /
 * COD) stay the always-on default; online payment is OFF until a gateway key
 * exists (doc 04 §5.1). Booleans are stored as '1'/'0' strings with type=boolean
 * so the reader casts them consistently.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // --- Store identity (general) -----------------------------------
            ['general', 'store_name', 'قصاقيص أطفال', 'string'],
            ['general', 'tagline', 'كتبٌ مختارة بحب تزرع القيم وتُشعل خيال أطفالنا', 'string'],
            ['general', 'hero_title', 'حكايات تكبر مع أطفالكم', 'string'],
            ['general', 'hero_subtitle', 'اكتشفوا مكتبة من كتب الأطفال المنسّقة بعناية — قصص تُمتع وتربّي وتغرس أجمل القيم في قلوب صغارنا.', 'text'],
            ['general', 'currency', 'EGP', 'string'],

            // --- Contact (contact) ------------------------------------------
            // Placeholder WhatsApp number (E.164, no leading +). NOT a real line —
            // the admin sets the store's actual number from the panel.
            ['contact', 'whatsapp_number', '201000000000', 'string'],
            ['contact', 'contact_phone', '', 'string'],
            ['contact', 'contact_email', 'info@qasaqis.store', 'string'],
            ['contact', 'contact_address', '', 'string'],

            // --- Social media (social) — empty, ready for the admin to fill --
            ['social', 'social_facebook', '', 'string'],
            ['social', 'social_instagram', '', 'string'],
            ['social', 'social_tiktok', '', 'string'],
            ['social', 'social_youtube', '', 'string'],
            ['social', 'social_twitter', '', 'string'],
            ['social', 'social_snapchat', '', 'string'],
            ['social', 'social_telegram', '', 'string'],

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
