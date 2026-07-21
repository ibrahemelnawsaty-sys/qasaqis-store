<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Enums\BackgroundPattern;
use App\Enums\PatternSurface;
use App\Models\Book;
use App\Models\Page;
use App\Models\Setting;
use App\Services\Cms\BackgroundPatternService;
use Database\Factories\PageFactory;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PublisherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * نقش الخلفية صار محتوى CMS يتحكم فيه الأدمن (الدستور 0.8): لكل صفحة نقش،
 * ولكل قسم في الرئيسية نقش اختياري، ولكل صفحة CMS تجاوز خاص بها.
 *
 * هذا الحارس يغطّي ما ينكسر صامتًا:
 *   - نقش مضبوط في الإعدادات لا يصل إلى <body>.
 *   - الرجوع للافتراضي حين لا اختيار (فلا يتغيّر شكل الموقع بمجرد النشر).
 *   - الفرق بين «لم يُحفظ شيء» و«اختار الأدمن بلا نقش» — وهما مختلفان عمدًا.
 *   - قيمة غير صالحة في قاعدة البيانات تُسقط النقش لا الصفحة.
 *   - كل حالة في الـenum لها قاعدة CSS مقابلة (وإلا اختفى النقش بلا خطأ).
 *
 * HONESTY (1.3/1.5): NOT executed in this PHP-less environment; runs on the
 * hosting via `php artisan test`.
 */
final class BackgroundPatternCmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CategorySeeder::class, PublisherSeeder::class]);
    }

    private function setPattern(PatternSurface $surface, ?string $value): void
    {
        Setting::updateOrCreate(
            ['key' => $surface->settingKey()],
            ['group' => 'appearance', 'value' => $value, 'type' => 'string',
                'is_encrypted' => false, 'autoload' => true],
        );

        app(BackgroundPatternService::class)->flush();
    }

    public function test_home_falls_back_to_its_default_when_nothing_is_configured(): void
    {
        Book::factory()->count(3)->create();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('class="pat-storybook-lattice"', false);
    }

    public function test_admin_choice_replaces_the_page_pattern(): void
    {
        Book::factory()->count(3)->create();
        $this->setPattern(PatternSurface::PageHome, BackgroundPattern::DotsAndArcs->value);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('class="pat-dots-and-arcs"', false)
            ->assertDontSee('pat-storybook-lattice');
    }

    public function test_admin_can_remove_the_pattern_entirely(): void
    {
        Book::factory()->count(3)->create();
        $this->setPattern(PatternSurface::PageHome, BackgroundPattern::None->value);

        // 'none' اختيار صريح: لا فئة نقش إطلاقًا، لا رجوع للافتراضي.
        // نؤكّد على وسم <body> تحديدًا لا على class="" في أي مكان.
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('pat-storybook-lattice')
            ->assertSee('<body x-data="shell" class="">', false);
    }

    public function test_a_corrupt_stored_value_degrades_to_no_pattern_not_an_error(): void
    {
        Book::factory()->count(3)->create();
        $this->setPattern(PatternSurface::PageHome, 'قيمة-غير-موجودة');

        $this->get(route('home'))->assertOk();

        $this->assertSame(
            BackgroundPattern::None,
            app(BackgroundPatternService::class)->for(PatternSurface::PageHome),
        );
    }

    public function test_a_homepage_section_renders_a_band_only_when_configured(): void
    {
        // نقش الخلفية صار لكل قسم كتب على حدة (عمود homepage_sections.background_pattern،
        // يضبطه الأدمن من «أقسام كتب الرئيسية») بدل إعداد عامّ واحد — أدقّ وأوضح.
        // القسم يُعرض فقط عند وجود كتب، ويُلَفّ بالشريط فقط إذا ضُبط له نقش.
        Book::factory()->count(3)->create(['is_published' => true, 'published_at' => now()]);
        $section = \App\Models\HomepageSection::create([
            'title' => 'قسم كتب', 'source_type' => 'latest', 'item_limit' => 8,
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->get(route('home'))->assertOk()->assertDontSee('sec-band');

        $section->update(['background_pattern' => BackgroundPattern::BookFans->value]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('sec-band pat-book-fans', false);
    }

    public function test_a_cms_page_overrides_the_static_pages_pattern(): void
    {
        $page = PageFactory::new()->create([
            'is_published' => true,
            'background_pattern' => BackgroundPattern::ScrapsConfetti->value,
        ]);

        $this->get(route('pages.show', $page))
            ->assertOk()
            ->assertSee('class="pat-scraps-confetti"', false);
    }

    public function test_a_cms_page_without_a_choice_follows_the_static_pages_setting(): void
    {
        $page = PageFactory::new()->create([
            'is_published' => true,
            'background_pattern' => null,
        ]);
        $this->setPattern(PatternSurface::PageStatic, BackgroundPattern::DotsAndArcs->value);

        $this->get(route('pages.show', $page))
            ->assertOk()
            ->assertSee('class="pat-dots-and-arcs"', false);
    }

    /**
     * لكل نقش في الـenum قاعدة CSS نهارية وأخرى ليلية. بدون هذا الفحص يمكن
     * أن يُضاف نقش للقائمة فيختاره الأدمن ولا يظهر شيء — بلا أي خطأ.
     */
    public function test_every_pattern_in_the_enum_has_light_and_dark_css_rules(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        foreach (BackgroundPattern::cases() as $pattern) {
            if ($pattern === BackgroundPattern::None) {
                continue;
            }

            $class = $pattern->cssClass();

            $this->assertStringContainsString(
                '.'.$class.'{',
                $css,
                "النقش [{$pattern->value}] بلا قاعدة CSS نهارية."
            );

            $this->assertStringContainsString(
                '[data-theme="dark"] .'.$class.'{',
                $css,
                "النقش [{$pattern->value}] بلا نسخة ليلية — سيختفي على الخلفية الداكنة."
            );
        }
    }

    public function test_every_surface_default_is_a_real_pattern(): void
    {
        foreach (PatternSurface::cases() as $surface) {
            $this->assertInstanceOf(BackgroundPattern::class, $surface->default());
            $this->assertNotEmpty($surface->label());
            $this->assertStringStartsWith('pattern.', $surface->settingKey());
        }
    }
}
