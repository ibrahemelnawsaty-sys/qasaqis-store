<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Book;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PublisherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * لكل قسم نقش خلفية خاص به، يُطبَّق بفئة على <body> عبر @section('body_class')
 * ويُعرَّف في app.css. هذا الحارس يمنع أمرين:
 *   1) صفحة تفقد نقشها بصمت عند إعادة تحرير قالبها.
 *   2) فئة تُكتب في قالب بلا قاعدة مقابلة في app.css (نقش يختفي دون خطأ).
 *
 * ملاحظة: النسخة الليلية لكل نقش تعيش في app.css تحت
 * :root[data-theme="dark"] و prefers-color-scheme، ويغطّيها فحص الأصول لا هذا الاختبار.
 *
 * HONESTY (1.3/1.5): NOT executed in this PHP-less environment; runs on the
 * hosting via `php artisan test`.
 */
final class SectionPatternTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CategorySeeder::class, PublisherSeeder::class]);
    }

    private function assertPattern(string $routeName, string $class): void
    {
        Book::factory()->count(3)->create();

        $this->get(route($routeName))
            ->assertOk()
            ->assertSee('class="'.$class.'"', false);
    }

    public function test_home_carries_the_storybook_lattice(): void
    {
        $this->assertPattern('home', 'pat-storybook-lattice');
    }

    public function test_catalog_carries_the_book_fans(): void
    {
        $this->assertPattern('books.index', 'pat-book-fans');
    }

    public function test_blog_carries_the_calligraphic_curls(): void
    {
        $this->assertPattern('blog.index', 'pat-calligraphic-curls');
    }

    public function test_cart_carries_the_quietest_pattern(): void
    {
        $this->assertPattern('cart.show', 'pat-dots-and-arcs');
    }

    public function test_book_page_carries_the_scissors_pattern(): void
    {
        $book = Book::factory()->create(['is_published' => true]);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('class="pat-scissors-trails"', false);
    }

    /**
     * كل فئة نقش تُستعمل في أي قالب يجب أن تكون معرّفة فعلًا في app.css،
     * وإلا ظهرت الصفحة بلا نقش دون أي خطأ يكشف ذلك.
     */
    public function test_every_pattern_class_used_in_views_is_defined_in_the_stylesheet(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);

        $used = [];
        $views = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($views as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            preg_match_all(
                "/@section\('body_class',\s*'([^']+)'\)/",
                (string) file_get_contents($file->getPathname()),
                $m
            );

            foreach ($m[1] as $class) {
                $used[$class] = true;
            }
        }

        $this->assertNotEmpty($used, 'لم يُعثر على أي فئة نقش في القوالب.');

        foreach (array_keys($used) as $class) {
            $this->assertStringContainsString(
                '.'.$class.'{',
                $css,
                "الفئة [{$class}] مستعملة في قالب لكنها غير معرّفة في app.css."
            );
        }
    }
}
