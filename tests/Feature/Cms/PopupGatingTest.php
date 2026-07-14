<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use Database\Factories\PopupFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Server-side pop-up gating (constitution 0.8). PopupService::forRequest is the
 * authoritative gate wired into the storefront layout composer, so its decision
 * shows up in the rendered home page: an active pop-up inside its schedule window
 * renders its title; a disabled one, or one outside its starts_at/ends_at window,
 * does not. (Device/trigger/frequency are client-side only and out of scope here.)
 *
 * The Popup model has no HasFactory trait, so PopupFactory::new() is used directly
 * rather than Popup::factory() — the tests must not modify application code.
 * Cache is flushed per test because PopupService briefly caches the active
 * candidate list (cms.popups.active), so a stale list must never leak between cases.
 *
 * HONESTY (1.3/1.5): NOT executed in this PHP-less environment; runs under
 * `php artisan test` on the hosting.
 */
final class PopupGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_active_popup_within_schedule_is_shown_on_home(): void
    {
        PopupFactory::new()->withinSchedule()->create([
            'title' => 'عرض الصيف المميز',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('عرض الصيف المميز');
    }

    public function test_inactive_popup_is_not_shown(): void
    {
        PopupFactory::new()->inactive()->create([
            'title' => 'نافذة معطّلة',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('نافذة معطّلة');
    }

    public function test_popup_before_its_start_window_is_not_shown(): void
    {
        PopupFactory::new()->notStarted()->create([
            'title' => 'نافذة لم تبدأ بعد',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('نافذة لم تبدأ بعد');
    }

    public function test_popup_after_its_end_window_is_not_shown(): void
    {
        PopupFactory::new()->ended()->create([
            'title' => 'نافذة منتهية',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('نافذة منتهية');
    }
}
