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
 *
 * COVERAGE INVARIANT (constitution 0.8, docs/10 §315): every key seeded here MUST
 * also be editable from ManageStoreSettings — a seeded value the owner cannot change
 * from the panel is a CMS gap. Tests\Feature\Admin\SettingsCoverageTest enforces this
 * and fails on any key added here without a matching field on the settings page, so
 * keep the two in step (same key, same group, same type).
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
            ['contact', 'whatsapp_number', '201018818720', 'string'],
            ['contact', 'contact_phone', '', 'string'],
            ['contact', 'contact_email', 'info@qasaqis.store', 'string'],
            ['contact', 'contact_address', '6 أكتوبر، محافظة الجيزة، الحي الرابع', 'string'],
            ['contact', 'store_maps_url', 'https://maps.app.goo.gl/C6P3GVQdRiVuEdK4A', 'string'],

            // --- Shipping (shipping) ----------------------------------------
            // `shipping_note` moved out of the `contact` group: it is shipping copy,
            // and it now sits beside the threshold in the panel's «الشحن» section.
            // Behaviour-neutral: nothing reads `settings` by group except the
            // appearance/pattern rows (BackgroundPatternService); every other reader
            // looks rows up by `key`. `group` is declared in the settings migration
            // as general/contact/payment/shipping/social, so `shipping` is not new.
            ['shipping', 'shipping_note', 'شحن دولي لكل الدول', 'text'],

            // Free-shipping threshold in EGP, measured on the order total AFTER the
            // discount. Ships EMPTY on purpose: empty = no threshold at all. No amount
            // is invented here (constitution 0.4 / 1.1) — the store owner sets the real
            // figure from the panel.
            ['shipping', 'free_shipping_threshold', '', 'string'],

            // --- Social media (social) — empty, ready for the admin to fill --
            ['social', 'social_facebook', 'https://www.facebook.com/groups/1596310100703409/', 'string'],
            ['social', 'social_instagram', 'https://www.instagram.com/qsaqis_kids/', 'string'],
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
            ['payment', 'cod_enabled', '0', 'boolean'],
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
