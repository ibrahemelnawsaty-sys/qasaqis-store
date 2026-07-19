<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * هجرة تحويل أيقونات «ليه الأمهات بيحبونا» من إيموجي إلى مفاتيح مكتبة.
 *
 * القرار المقصود الذي تحرسه هذه الاختبارات: نحوّل الإيموجي الخمسة المزروعة فقط،
 * ونترك أي إيموجي آخر أضافه الأدمن **كما هو**. تخمين مفتاح لإيموجي لا نعرفه يضع
 * أيقونة خاطئة في بطاقة المستخدم، وذلك أسوأ من إبقاء الإيموجي يعمل (أمانة المحتوى).
 *
 * نستدعي up()/down() على نسخة الهجرة مباشرةً: RefreshDatabase يكون قد شغّلها سلفًا،
 * فنُدخل صفوفًا قديمة ثم نُعيد تشغيلها عليها.
 *
 * أمانة (1.3/1.5): لم تُنفَّذ هنا — لا PHP في هذه البيئة. تُشغَّل على الاستضافة.
 */
final class WhyItemIconMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_07_19_000006_convert_why_item_icons_to_keys.php');
    }

    private function seedRow(string $icon, string $title): int
    {
        return (int) DB::table('why_items')->insertGetId([
            'icon' => $icon,
            'title' => $title,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function iconOf(int $id): string
    {
        return (string) DB::table('why_items')->where('id', $id)->value('icon');
    }

    public function test_it_converts_the_seeded_emoji_to_library_keys(): void
    {
        $ids = [
            '🎯' => $this->seedRow('🎯', 'مختارة بعناية'),
            '🔤' => $this->seedRow('🔤', 'لغة سليمة'),
            '🎨' => $this->seedRow('🎨', 'رسوم مبهجة'),
            '💰' => $this->seedRow('💰', 'أسعار في المتناول'),
            '💛' => $this->seedRow('💛', 'الافتراضية'),
        ];

        $this->migration()->up();

        $this->assertSame('target-curated', $this->iconOf($ids['🎯']));
        $this->assertSame('harakat-letter', $this->iconOf($ids['🔤']));
        $this->assertSame('pigment-sweep', $this->iconOf($ids['🎨']));
        $this->assertSame('value-tag', $this->iconOf($ids['💰']));
        $this->assertSame('heart-care', $this->iconOf($ids['💛']));
    }

    /** الضمانة الأهمّ: إيموجي لا نعرفه لا يُخمَّن له مفتاح — يبقى كما هو. */
    public function test_it_leaves_an_unknown_admin_emoji_untouched(): void
    {
        $id = $this->seedRow('🚚', 'شحن سريع');

        $this->migration()->up();

        $this->assertSame('🚚', $this->iconOf($id));
    }

    /** ولا تمسّ بطاقةً حُوّلت سلفًا لو أُعيد تشغيل الهجرة. */
    public function test_it_is_idempotent_over_already_converted_rows(): void
    {
        $id = $this->seedRow('shield-trust', 'ثقة');

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame('shield-trust', $this->iconOf($id));
    }

    public function test_down_restores_the_original_emoji(): void
    {
        $converted = $this->seedRow('🎯', 'مختارة بعناية');
        $untouched = $this->seedRow('🚚', 'شحن سريع');

        $migration = $this->migration();
        $migration->up();
        $migration->down();

        $this->assertSame('🎯', $this->iconOf($converted));
        $this->assertSame('🚚', $this->iconOf($untouched));
    }
}
