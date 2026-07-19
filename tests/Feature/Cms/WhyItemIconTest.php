<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Filament\Resources\WhyItemResource;
use App\Models\WhyItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * أيقونات قسم «ليه الأمهات بيحبونا». الضمانة المركزية هنا ليست شكل الأيقونة بل
 * أمانة المحتوى (الدستور 1.x): بطاقة أضافها الأدمن بإيموجي قبل وجود المكتبة يجب
 * أن تبقى معروضة **وقابلة للتعديل** — لا أن تُجبر الواجهة الأدمن على استبدالها.
 *
 * مراجعة عدائية كشفت أن الرجوع الآمن كان يغطّي مسار العرض وحده: القائمة المنسدلة
 * بقيمة خارج options تظهر فارغة، ومع required() يمتنع الحفظ. هذه الاختبارات تُثبّت
 * الإصلاح فلا يعود العيب صامتًا.
 *
 * أمانة (1.3/1.5): لم تُنفَّذ هنا — لا PHP في هذه البيئة. تُشغَّل على الاستضافة
 * بـ `php artisan test --filter=WhyItemIconTest`.
 */
final class WhyItemIconTest extends TestCase
{
    use RefreshDatabase;

    private function renderIcon(string $name): string
    {
        return Blade::render('<x-why-icon :name="$name" />', ['name' => $name]);
    }

    // ---- عقد المكتبة ----------------------------------------------------

    /**
     * كل مفتاح تعرضه قائمة الأدمن يجب أن يوجد في المكوّن. لو انزلق أحدهما عن
     * الآخر فسيختار الأدمن أيقونة تُطبع في الواجهة نصًّا خامًا («delivery-truck»)
     * بدل رسم — وهو عطب صامت لا يرفع خطأً.
     */
    public function test_every_admin_option_renders_a_real_icon(): void
    {
        foreach (array_keys(WhyItemResource::iconOptions()) as $key) {
            $html = $this->renderIcon($key);

            $this->assertStringContainsString('<svg', $html, "المفتاح «{$key}» لا يقابله رسم في المكوّن.");
            $this->assertStringNotContainsString($key, $html, "المفتاح «{$key}» طُبع نصًّا بدل أن يُرسم.");
        }
    }

    /** المكتبة كلها currentColor: لا لون صريح وإلا انكسر الوضع الداكن. */
    public function test_icons_carry_no_hardcoded_colour(): void
    {
        foreach (array_keys(WhyItemResource::iconOptions()) as $key) {
            $html = $this->renderIcon($key);

            $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $html, "«{$key}» يحمل لونًا صريحًا.");
            $this->assertStringNotContainsString('var(--', $html, "«{$key}» يحمل var() داخل الـSVG.");
        }
    }

    /**
     * معرّفات التدرّجات تتصادم حين تُعرض عدّة أيقونات في صفحة واحدة: المتصفّح
     * يستعمل أوّل id لكل ما يشير إليه. المكتبة تتجنّب ذلك بألّا تحمل id إطلاقًا.
     */
    public function test_icons_declare_no_ids_that_could_collide(): void
    {
        foreach (array_keys(WhyItemResource::iconOptions()) as $key) {
            $this->assertStringNotContainsString(' id="', $this->renderIcon($key), "«{$key}» يحمل id.");
        }
    }

    // ---- الرجوع الآمن في العرض -----------------------------------------

    /** قيمة غير معروفة (إيموجي قديم) تُطبع كما هي فلا تختفي البطاقة. */
    public function test_unknown_value_is_printed_verbatim_instead_of_vanishing(): void
    {
        $html = $this->renderIcon('🚀');

        $this->assertStringContainsString('🚀', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    // ---- الرجوع الآمن في التحرير (العيب الذي كشفته المراجعة) ------------

    /** صفحة الإنشاء ($record فارغ): المكتبة وحدها، فلا تتسرّب قيم قديمة. */
    public function test_create_page_offers_library_keys_only(): void
    {
        $this->assertSame(
            WhyItemResource::iconOptions(),
            WhyItemResource::iconOptionsFor(null),
        );
    }

    /**
     * جوهر الإصلاح: إيموجي الأدمن يبقى **خيارًا قائمًا** في نموذج التعديل.
     * بدونه يظهر الحقل فارغًا و required() يمنع أي حفظ حتى يستبدله.
     */
    public function test_legacy_emoji_stays_selectable_when_editing(): void
    {
        $item = WhyItem::factory()->create(['icon' => '🚚']);

        $options = WhyItemResource::iconOptionsFor($item);

        $this->assertArrayHasKey('🚚', $options, 'إيموجي الأدمن سقط من القائمة فيُجبَر على استبداله.');
        $this->assertSame('🚚', array_key_first($options), 'قيمته الحالية يجب أن تتصدّر القائمة.');

        // ولا تُفقد بقية المكتبة، فيظلّ بوسعه الترقية متى شاء.
        foreach (array_keys(WhyItemResource::iconOptions()) as $key) {
            $this->assertArrayHasKey($key, $options);
        }
    }

    /** مفتاح معروف لا يُضاعَف ولا يُعاد ترتيبه. */
    public function test_known_key_is_not_duplicated_when_editing(): void
    {
        $item = WhyItem::factory()->create(['icon' => 'shield-trust']);

        $this->assertSame(
            WhyItemResource::iconOptions(),
            WhyItemResource::iconOptionsFor($item),
        );
    }

    // ---- التكامل مع الرئيسية -------------------------------------------

    public function test_homepage_draws_the_card_icon(): void
    {
        WhyItem::query()->delete();
        WhyItem::factory()->create([
            'icon' => 'harakat-letter',
            'title' => 'لغة سليمة ومشكّلة',
            'is_active' => true,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('لغة سليمة ومشكّلة');
        // الرسم حاضر، والمفتاح لم يتسرّب نصًّا إلى الصفحة.
        $response->assertDontSee('harakat-letter', escape: false);
    }
}
