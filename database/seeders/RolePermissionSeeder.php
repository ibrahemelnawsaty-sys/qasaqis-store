<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and atomic permissions (doc 04, sections 2-3). Permission names use the
 * `{resource}.{action}` scheme from the spec (e.g. products.*, coupons.*).
 *
 * super_admin is ALSO bypassed via Gate::before in the app, but we still grant it
 * every permission so authorization works even before that gate is wired.
 * Least-privilege is applied per role (constitution 4.4). Guard: web (Filament v3).
 */
class RolePermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    /**
     * The complete atomic permission catalogue (doc 04 §3.1-3.6).
     *
     * @var array<int, string>
     */
    private const PERMISSIONS = [
        // 3.1 Products & content.
        'products.view', 'products.create', 'products.update',
        'products.delete', 'products.archive', 'products.price.update',
        'products.inventory.manage',
        'publishers.view', 'publishers.manage',
        'categories.view', 'categories.manage',
        'sections.view', 'sections.manage', 'sections.assign_product',
        // 3.2 Homepage & CMS.
        'homepage.view', 'homepage.edit', 'homepage.blocks.manage',
        'pages.view', 'pages.create', 'pages.update', 'pages.delete',
        'menus.view', 'menus.manage', 'menus.links.manage',
        'banners.view', 'banners.manage', 'sliders.manage',
        'media.view', 'media.upload', 'media.delete',
        // 3.3 Marketing & SEO.
        'coupons.view', 'coupons.manage',
        'popups.view', 'popups.manage', 'surveys.manage',
        'referrals.manage',
        'seo.view', 'seo.edit', 'seo.technical.manage',
        // الحملات البريدية: عرض السجل، الإرسال، وإدارة قائمة الحظر (إلغاء الاشتراك).
        'campaigns.view', 'campaigns.send', 'campaigns.suppressions.manage',
        // 3.4 Orders & payment.
        'orders.view', 'orders.view_financials', 'orders.update_status',
        'orders.ship', 'orders.cancel', 'orders.refund', 'orders.export',
        'payment_proof.view', 'payment_proof.review',
        'payments.methods.toggle', 'payments.settings',
        'payments.manual_accounts.manage',
        // إدارة الشحن الدولي: الدول ومناطق الشحن (M5).
        'shipping.view', 'shipping.create', 'shipping.update', 'shipping.delete',
        // 3.5 Reviews & support.
        'reviews.view', 'reviews.reply', 'reviews.moderate',
        'comments.view', 'comments.reply', 'comments.moderate',
        'inquiries.view', 'inquiries.respond',
        // 3.6 Users & system.
        'users.view', 'users.manage', 'users.assign_roles',
        'roles.view', 'roles.manage',
        'settings.view', 'settings.general.edit', 'settings.integrations.manage',
        'system.logs.view', 'system.maintenance', 'system.backup', 'system.cache.clear',
    ];

    public function run(): void
    {
        // Ensure fresh reads while seeding (spatie caches permissions).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }

        $all = self::PERMISSIONS;

        // Helper: every permission equal to, or namespaced under, a prefix.
        $byPrefix = static function (array $prefixes) use ($all): array {
            return array_values(array_filter($all, static function (string $p) use ($prefixes): bool {
                foreach ($prefixes as $pre) {
                    if ($p === $pre || str_starts_with($p, $pre.'.')) {
                        return true;
                    }
                }

                return false;
            }));
        };

        $roles = [
            // Full control — granted everything (also bypassed by Gate::before).
            'super_admin' => $all,

            // Broad content/products/orders, minus roles, API keys, deep tech.
            'admin' => array_merge(
                $byPrefix([
                    'products', 'publishers', 'categories', 'sections',
                    'homepage', 'pages', 'menus', 'banners', 'media',
                    'coupons', 'popups', 'surveys', 'referrals',
                    'orders', 'payment_proof', 'reviews', 'comments', 'inquiries',
                    'shipping', 'campaigns',
                ]),
                [
                    'seo.view', 'seo.edit',
                    'payments.methods.toggle', 'payments.manual_accounts.manage',
                    'users.view', 'users.manage',
                    'settings.view', 'settings.general.edit',
                    // سجل التدقيق «من غيّر ماذا ومتى» (M8) — المالك/الأدمن يراه.
                    'system.logs.view',
                ],
            ),

            // Technical/system, integrations and payment API keys.
            'it' => array_merge(
                $byPrefix(['settings', 'system']),
                [
                    'seo.technical.manage',
                    'payments.settings', 'payments.methods.toggle',
                    'media.view', 'orders.view',
                ],
            ),

            // Order lifecycle, shipping and proof review.
            'orders_manager' => array_merge(
                $byPrefix(['orders', 'shipping']),
                [
                    'payment_proof.view', 'payment_proof.review',
                    'products.view', 'products.inventory.manage',
                    'inquiries.view', 'inquiries.respond',
                ],
            ),

            // Content only — no sensitive pricing publish / delete.
            'content_editor' => array_merge(
                $byPrefix([
                    'categories', 'sections', 'homepage', 'pages',
                    'menus', 'banners', 'media',
                ]),
                [
                    'products.view', 'products.create', 'products.update',
                    'products.archive', 'products.price.update',
                    'publishers.view', 'publishers.manage',
                    'sliders.manage',
                    'seo.view', 'seo.edit',
                    'reviews.view', 'reviews.moderate',
                ],
            ),

            // Marketing & SEO only.
            'marketing' => array_merge(
                $byPrefix(['coupons', 'popups', 'campaigns']),
                [
                    'surveys.manage', 'referrals.manage',
                    'banners.manage', 'sliders.manage',
                    'seo.view', 'seo.edit',
                    'products.price.update',
                    'homepage.blocks.manage',
                ],
            ),

            // Restricted support — scoped further to allowed products via Policy.
            'support' => [
                'reviews.view', 'reviews.reply', 'reviews.moderate',
                'comments.view', 'comments.reply', 'comments.moderate',
                'inquiries.view', 'inquiries.respond',
                'orders.view',
            ],
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => self::GUARD]);
            // syncPermissions keeps the role authoritative and idempotent on re-run.
            $role->syncPermissions(array_values(array_unique($permissions)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
