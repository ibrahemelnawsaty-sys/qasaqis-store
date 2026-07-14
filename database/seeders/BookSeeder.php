<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Book;
use App\Models\BookImage;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\SeoMeta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Seeds the 23 real books from database/seed/books.json (constitution 0.3).
 *
 * Honest handling of missing content (constitution 0.4 / 1.1):
 *  - BOOK1 «أنا لستُ شقيًا!» has NO price      => price stays NULL (never invented).
 *  - BOOK10 «هون عليك»       has NO cover      => cover_image NULL, no image rows.
 *  - A book with no visible publisher links to the default house, never a guess.
 *
 * Prices are stored as DECIMAL(10,2) via the model cast — never float.
 * The normalized FULLTEXT search_index is rebuilt from the loaded relations.
 */
class BookSeeder extends Seeder
{
    /**
     * Exact publisher string in books.json => canonical Publisher name.
     * Only these were confirmed on covers; everything else falls back to default.
     *
     * @var array<string, string>
     */
    private const PUBLISHER_MAP = [
        'سِجرة' => 'سِجرة',
        'دار الشروق' => 'دار الشروق',
        'بيت الحكمة (سوريا)' => 'بيت الحكمة',
        'زغلول' => 'زغلول',
        'دار النون' => 'دار النون',
        'رؤية للنشر والإنتاج الإبداعي' => 'رؤية للنشر',
        'MOON' => 'MOON',
        '80Fekra (ثمانون فكرة)' => '80Fekra',
    ];

    public function run(): void
    {
        $path = database_path('seed/books.json');

        if (! File::exists($path)) {
            $this->command?->error("books.json not found at {$path} — BookSeeder skipped.");

            return;
        }

        /** @var array<int, array<string, mixed>> $books */
        $books = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        // Preload lookups once (avoids N+1 inside the loop).
        $categories = Category::pluck('id', 'name');
        $publishers = Publisher::pluck('id', 'name');
        $defaultPublisherId = $publishers[PublisherSeeder::DEFAULT_PUBLISHER] ?? null;

        foreach ($books as $index => $data) {
            $categoryId = $categories[$data['category']] ?? null;

            if ($categoryId === null) {
                $this->command?->warn("Unknown category '{$data['category']}' for {$data['folder']} — skipped.");

                continue;
            }

            $publisherId = $this->resolvePublisherId($data['publisher'] ?? '', $publishers, $defaultPublisherId);

            [$ageMin, $ageMax] = $this->parseAges((string) ($data['age_range'] ?? ''));

            // Price: BOOK1 (needs_price / price 0) stays NULL — no invented value.
            // Pass the raw value through; the model's decimal:2 cast stores it as
            // DECIMAL(10,2) (never float maths on money).
            $price = ! empty($data['price_egp']) ? $data['price_egp'] : null;
            // Struck-through original price only when a real offer price exists.
            $oldPrice = ! empty($data['old_price_egp']) ? $data['old_price_egp'] : null;

            $cover = trim((string) ($data['cover_image'] ?? ''));
            $coverImage = $cover !== '' ? $cover : null; // BOOK10 => NULL placeholder.

            /** @var Book $book */
            $book = Book::updateOrCreate(
                ['slug' => $data['slug_ar']],
                [
                    'category_id' => $categoryId,
                    'publisher_id' => $publisherId,
                    'title' => $data['title'], // mutator fills title_normalized.
                    'author' => $this->nullIfBlank($data['author'] ?? null),
                    'illustrator' => $this->nullIfBlank($data['illustrator'] ?? null),
                    'short_description' => $this->nullIfBlank($data['short_description'] ?? null),
                    'long_description' => $this->nullIfBlank($data['long_description_html'] ?? null),
                    'price' => $price,
                    'old_price' => $oldPrice,
                    'age_min' => $ageMin,
                    'age_max' => $ageMax,
                    'age_label' => $this->nullIfBlank($data['age_range'] ?? null),
                    'learning_outcomes' => $data['learning_outcomes'] ?? [],
                    'cover_image' => $coverImage,
                    'stock_status' => 'in_stock',
                    'is_published' => true,
                    'is_featured' => false,
                    'published_at' => now(),
                    'sort_order' => $index + 1,
                ],
            );

            // Keep the primary category mirrored into the many-to-many pivot
            // so category filtering works through either relation.
            $book->categories()->sync([$categoryId]);

            // Rebuild the Arabic normalized FULLTEXT blob from loaded relations.
            $book->setRelation('category', Category::find($categoryId));
            $book->setRelation('publisher', $publisherId ? Publisher::find($publisherId) : null);
            $book->refreshSearchIndex();
            $book->save();

            $this->syncImages($book, $coverImage, $data['gallery_images'] ?? []);
            $this->syncSeo($book, $data);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $publishers
     */
    private function resolvePublisherId(string $raw, $publishers, ?int $default): ?int
    {
        $raw = trim($raw);

        if ($raw === '') {
            return $default; // No visible publisher => default house.
        }

        $canonical = self::PUBLISHER_MAP[$raw] ?? null;

        // Unmapped but non-empty: fall back to default rather than guessing.
        return $canonical ? ($publishers[$canonical] ?? $default) : $default;
    }

    /**
     * Parse "4 - 8 سنوات" style ranges. Non-numeric ranges leave both NULL
     * (age_label keeps the original text); a single number fills only age_min.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function parseAges(string $range): array
    {
        if (preg_match('/(\d+)\s*[-–—]\s*(\d+)/u', $range, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        if (preg_match('/(\d+)/u', $range, $m)) {
            return [(int) $m[1], null];
        }

        return [null, null];
    }

    /**
     * @param  array<int, string>  $gallery
     */
    private function syncImages(Book $book, ?string $cover, array $gallery): void
    {
        if ($cover !== null) {
            BookImage::updateOrCreate(
                ['book_id' => $book->id, 'path' => $cover],
                ['collection' => 'cover', 'is_cover' => true, 'alt' => $book->title, 'sort_order' => 0],
            );
        }

        foreach (array_values($gallery) as $i => $path) {
            $path = trim((string) $path);

            if ($path === '') {
                continue;
            }

            BookImage::updateOrCreate(
                ['book_id' => $book->id, 'path' => $path],
                ['collection' => 'gallery', 'is_cover' => false, 'alt' => $book->title, 'sort_order' => $i + 1],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncSeo(Book $book, array $data): void
    {
        SeoMeta::updateOrCreate(
            ['seoable_type' => $book->getMorphClass(), 'seoable_id' => $book->id],
            [
                'meta_title' => $this->nullIfBlank($data['seo_title'] ?? null),
                'meta_description' => $this->nullIfBlank($data['seo_description'] ?? null),
                'og_title' => $this->nullIfBlank($data['seo_title'] ?? null),
                'og_description' => $this->nullIfBlank($data['seo_description'] ?? null),
                'robots' => 'index,follow',
            ],
        );
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return ($value === null || $value === '') ? null : $value;
    }
}
