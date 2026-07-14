<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Popup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for the Popup model (CMS pop-up: دعاية/استبيان/نشرة/إعلان). Fields
 * mirror the create_popups_table migration EXACTLY: title, type(promo|survey|
 * newsletter|announcement), content, image_path, survey_id, cta_label, cta_url,
 * display_trigger(on_load default), delay_seconds, display_frequency
 * (once_per_session default), target_pages, target_devices, starts_at, ends_at,
 * is_active(true default), priority.
 *
 * Default: an active, unscheduled promo that targets every page (empty
 * target_pages) — i.e. it qualifies in PopupService::forRequest right now.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Popup>
 */
class PopupFactory extends Factory
{
    protected $model = Popup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'نافذة ترويجية '.fake()->unique()->numberBetween(1, 999999),
            'type' => 'promo',
            'content' => 'خصومات على مجموعة مختارة من كتب الأطفال.',
            'image_path' => null,
            'survey_id' => null,
            'cta_label' => 'تصفّح العروض',
            'cta_url' => '/books?sale=1',
            'display_trigger' => 'on_load',
            'delay_seconds' => null,
            'display_frequency' => 'once_per_session',
            'target_pages' => null,   // null/[] => every page.
            'target_devices' => null, // null/[] => every device.
            'starts_at' => null,      // null => no lower bound.
            'ends_at' => null,        // null => no upper bound.
            'is_active' => true,
            'priority' => 0,
        ];
    }

    /** Toggled off — excluded server-side by PopupService (is_active = false). */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /** Scheduled to start in the future (outside the window → not shown yet). */
    public function notStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(10),
        ]);
    }

    /** Already ended (outside the window → no longer shown). */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);
    }

    /** Explicitly within the schedule window (started yesterday, ends tomorrow). */
    public function withinSchedule(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
    }
}
