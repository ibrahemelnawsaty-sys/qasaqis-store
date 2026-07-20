<?php

declare(strict_types=1);

namespace App\Support\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * كاش قشرة المتجر: القيم المشتركة التي تُقرأ على كل صفحة (أقسام الملاحة، إعدادات
 * المتجر، قوائم الهيدر/الفوتر). تتغيّر نادرًا (تحرير من الـ CMS) فتُخزَّن مؤقّتًا
 * بمهلة قصيرة وتُبطَل صراحةً عند حفظ مصدرها عبر أحداث الموديل في AppServiceProvider.
 *
 * مفتاح واحد لكل مصدر — لا بيانات خاصّة بمستخدم هنا (آمن للمشاركة بين الجميع).
 * TTL يحدّ التقادم حتى لو فات مسار إبطال (مثل تغيّر عدد الكتب المنشورة في قسم).
 */
final class StorefrontCache
{
    /** أقسام الملاحة (Category + عدد الكتب المنشورة). */
    public const NAV_CATEGORIES = 'shell.nav_categories';

    /** إعدادات المتجر العامة (whatsapp/سوشيال/هوية) من جدول settings. */
    public const STORE_SETTINGS = 'shell.store_settings';

    /** مهلة قصيرة مشتركة (ثوانٍ) — نفس مرتبة PopupService/BackgroundPatternService. */
    public const TTL = 600;

    private const MENU_PREFIX = 'shell.menu.';

    /**
     * مواقع قوائم الـ CMS المدعومة — للإبطال الشامل عند حفظ أي قائمة/عنصر.
     * header = ملاحة الزائرة، header_customer = ملاحة العميلة المسجّلة، footer = التذييل.
     */
    private const MENU_LOCATIONS = ['header', 'header_customer', 'footer'];

    public static function menuKey(string $location): string
    {
        return self::MENU_PREFIX.$location;
    }

    public static function forgetNavCategories(): void
    {
        Cache::forget(self::NAV_CATEGORIES);
    }

    public static function forgetStoreSettings(): void
    {
        Cache::forget(self::STORE_SETTINGS);
    }

    public static function forgetMenus(): void
    {
        foreach (self::MENU_LOCATIONS as $location) {
            Cache::forget(self::menuKey($location));
        }
    }
}
