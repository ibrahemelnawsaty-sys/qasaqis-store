<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Book;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Services\Cms\HomepageSectionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * أقسام كتب الرئيسية: الحلّال (كل مصدر + الحد + الهجين المثبّت) وتصيير الرئيسية.
 */
class HomepageSectionsTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::factory()->create(['is_active' => true]);
    }

    private function book(string $title, array $extra = []): Book
    {
        return Book::factory()->create(array_merge([
            'title' => $title,
            'category_id' => $this->category->id,
            'is_published' => true,
            'published_at' => now(),
        ], $extra));
    }

    private function section(array $attrs): HomepageSection
    {
        return HomepageSection::create(array_merge([
            'source_type' => 'latest',
            'item_limit' => 8,
            'is_active' => true,
            'sort_order' => 0,
        ], $attrs));
    }

    private function resolver(): HomepageSectionResolver
    {
        return app(HomepageSectionResolver::class);
    }

    public function test_latest_orders_by_published_desc(): void
    {
        $old = $this->book('OldBook', ['published_at' => now()->subDays(10)]);
        $new = $this->book('NewBook', ['published_at' => now()->subDay()]);

        $ids = $this->resolver()->resolve($this->section(['title' => 'x', 'source_type' => 'latest']))->modelKeys();

        $this->assertSame([$new->id, $old->id], $ids);
    }

    public function test_featured_uses_sort_order_ascending_and_excludes_unflagged(): void
    {
        $a = $this->book('FeatA', ['is_featured' => true, 'sort_order' => 20]);
        $b = $this->book('FeatB', ['is_featured' => true, 'sort_order' => 10]);
        $this->book('Plain', ['is_featured' => false]);

        $ids = $this->resolver()->resolve($this->section(['title' => 'x', 'source_type' => 'featured']))->modelKeys();

        $this->assertSame([$b->id, $a->id], $ids);
    }

    public function test_manual_uses_pivot_position_order(): void
    {
        $b1 = $this->book('M1');
        $b2 = $this->book('M2');
        $b3 = $this->book('M3');
        $section = $this->section(['title' => 'x', 'source_type' => 'manual']);
        $section->books()->attach([$b3->id => ['position' => 1], $b1->id => ['position' => 2], $b2->id => ['position' => 3]]);

        $this->assertSame([$b3->id, $b1->id, $b2->id], $this->resolver()->resolve($section)->modelKeys());
    }

    public function test_pinned_books_lead_an_automatic_section_without_duplicates(): void
    {
        $pinned = $this->book('Pinned', ['published_at' => now()->subDays(30)]); // الأقدم — يكون أخيرًا عادةً
        $newest = $this->book('Newest', ['published_at' => now()->subDay()]);
        $section = $this->section(['title' => 'x', 'source_type' => 'latest']);
        $section->books()->attach([$pinned->id => ['position' => 1]]);

        $ids = $this->resolver()->resolve($section)->modelKeys();

        $this->assertSame($pinned->id, $ids[0]);            // المثبّت أولًا رغم قِدَمه
        $this->assertContains($newest->id, $ids);
        $this->assertSame(count($ids), count(array_unique($ids))); // بلا تكرار
    }

    public function test_item_limit_is_respected(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->book("B{$i}");
        }

        $this->assertCount(3, $this->resolver()->resolve($this->section(['title' => 'x', 'item_limit' => 3])));
    }

    public function test_bestsellers_falls_back_to_most_viewed_when_none_flagged(): void
    {
        $low = $this->book('Low', ['views_count' => 1, 'is_bestseller' => false]);
        $high = $this->book('High', ['views_count' => 99, 'is_bestseller' => false]);

        $ids = $this->resolver()->resolve($this->section(['title' => 'x', 'source_type' => 'bestsellers']))->modelKeys();

        $this->assertSame([$high->id, $low->id], $ids);
    }

    public function test_homepage_renders_active_sections_and_hides_inactive_and_empty(): void
    {
        $this->book('VisibleBook');
        $this->section(['title' => 'قسم ظاهر', 'source_type' => 'latest', 'sort_order' => 1]);
        $this->section(['title' => 'قسم معطّل', 'source_type' => 'latest', 'is_active' => false, 'sort_order' => 2]);
        $emptyCat = Category::factory()->create(['is_active' => true]);
        $this->section(['title' => 'قسم فارغ', 'source_type' => 'category', 'category_id' => $emptyCat->id, 'sort_order' => 3]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('قسم ظاهر', $html);
        $this->assertStringContainsString('VisibleBook', $html);
        $this->assertStringNotContainsString('قسم معطّل', $html);
        $this->assertStringNotContainsString('قسم فارغ', $html);
    }
}
