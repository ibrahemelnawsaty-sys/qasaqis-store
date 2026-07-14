<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Publisher;
use Illuminate\Database\Seeder;

/**
 * Real publishers observed on the book covers (constitution 0.5) plus a single
 * default house «قصص أطفال» that books with no visible publisher link to.
 * Publisher names are NEVER guessed for a book — unmatched/blank ones fall back
 * to the default in BookSeeder. name_normalized is filled by the model mutator.
 */
class PublisherSeeder extends Seeder
{
    /**
     * Canonical publisher name => URL-safe slug. The default house is created
     * first so it always exists as the fallback for books without a publisher.
     *
     * @var array<string, string>
     */
    public const PUBLISHERS = [
        'قصص أطفال'   => 'qasas-atfal',   // default house for books with no visible publisher.
        'سِجرة'       => 'sajara',
        'دار الشروق'  => 'dar-al-shorouk',
        'بيت الحكمة'  => 'bait-al-hikma',
        'زغلول'       => 'zaghloul',
        'دار النون'   => 'dar-al-noon',
        'رؤية للنشر'  => 'roya',
        'MOON'        => 'moon',
        '80Fekra'     => '80fekra',
    ];

    public const DEFAULT_PUBLISHER = 'قصص أطفال';

    public function run(): void
    {
        $order = 0;

        foreach (self::PUBLISHERS as $name => $slug) {
            Publisher::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name, // mutator fills name_normalized.
                    'is_active' => true,
                    'sort_order' => $order++,
                ],
            );
        }
    }
}
