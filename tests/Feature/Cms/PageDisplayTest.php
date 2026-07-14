<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use Database\Factories\PageFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CMS page visibility via pages.show ({page:slug} binding). Only PUBLISHED pages
 * are reachable; an unpublished draft must 404 so it never leaks (constitution
 * 0.8 CMS — mirrors PageController::show + Page::scopePublished).
 *
 * The Page model has no HasFactory trait, so the factory is instantiated
 * directly (PageFactory::new()) rather than via Page::factory() — the tests must
 * not modify application code.
 *
 * HONESTY (1.3/1.5): NOT executed in this PHP-less environment; runs under
 * `php artisan test` on the hosting.
 */
final class PageDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_page_returns_200_and_shows_its_title(): void
    {
        $page = PageFactory::new()->published()->create([
            'title' => 'من نحن',
        ]);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee('من نحن');
    }

    public function test_unpublished_draft_page_returns_404(): void
    {
        // The row exists (slug binding resolves) but the controller aborts 404.
        $page = PageFactory::new()->draft()->create();

        $this->get(route('pages.show', $page))->assertNotFound();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->get(route('pages.show', 'no-such-page'))->assertNotFound();
    }
}
