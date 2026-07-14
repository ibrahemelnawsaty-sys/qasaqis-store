<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The six fixed catalogue sections (constitution 0.3). All six MUST always exist —
 * including the currently-empty ones (روايات، كتب طفولة مبكرة) — and MUST NOT be
 * removed just because they hold no books yet. Slugs are explicit Latin/URL-safe
 * strings because Str::slug() strips pure-Arabic text to an empty string.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Order = display order. color_hex uses brand tokens (constitution 0.1).
        $categories = [
            ['name' => 'روايات',            'slug' => 'novels',            'color_hex' => '#5B2A86'],
            ['name' => 'كتب علمية',          'slug' => 'science',           'color_hex' => '#F27405'],
            ['name' => 'سلوكيات ومشاعر',     'slug' => 'behavior-emotions', 'color_hex' => '#D6336C'],
            ['name' => 'قصص',               'slug' => 'stories',           'color_hex' => '#F2B705'],
            ['name' => 'كتب طفولة مبكرة',    'slug' => 'early-childhood',   'color_hex' => '#5B2A86'],
            ['name' => 'كتب دينية',          'slug' => 'religious',         'color_hex' => '#F27405'],
        ];

        foreach ($categories as $index => $category) {
            // Idempotent: never truncates, safe to re-run (constitution 3.3).
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'parent_id' => null,
                    'color_hex' => $category['color_hex'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
