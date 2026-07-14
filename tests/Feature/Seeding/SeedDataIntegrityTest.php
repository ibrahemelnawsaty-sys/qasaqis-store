<?php

declare(strict_types=1);

namespace Tests\Feature\Seeding;

use App\Models\Book;
use App\Models\Category;
use App\Models\PaymentMethod;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PublisherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catalogue & payment seed integrity (constitution 0.3 / 0.4):
 *  - the six fixed sections always exist (even the empty ones);
 *  - exactly 23 books with the declared per-section distribution;
 *  - BOOK1 keeps a NULL price and BOOK10 a NULL cover — never invented;
 *  - payment_methods codes match the orders.payment_method enum exactly.
 *
 * HONESTY (1.3/1.5): NOT executed here (no PHP); runs via `php artisan test`.
 * BookSeeder requires database/seed/books.json (the real 23-book catalogue).
 */
final class SeedDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /** The exact codes allowed by the orders.payment_method enum (migration). */
    private const ORDER_PAYMENT_ENUM = [
        'cod', 'instapay', 'vodafone_cash', 'bank_transfer', 'online_gateway',
    ];

    /** Section slug => expected number of books (constitution 0.3). */
    private const SECTION_DISTRIBUTION = [
        'behavior-emotions' => 14,
        'science' => 5,
        'stories' => 3,
        'religious' => 1,
        'novels' => 0,           // kept even though empty
        'early-childhood' => 0,  // kept even though empty
    ];

    public function test_the_six_sections_all_exist_and_are_active(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertSame(6, Category::count());

        foreach (array_keys(self::SECTION_DISTRIBUTION) as $slug) {
            $category = Category::where('slug', $slug)->first();
            $this->assertNotNull($category, "Missing section: {$slug}");
            $this->assertTrue($category->is_active);
        }
    }

    public function test_catalogue_has_23_books_in_the_declared_distribution(): void
    {
        $this->seed([CategorySeeder::class, PublisherSeeder::class, BookSeeder::class]);

        $this->assertSame(23, Book::count());

        foreach (self::SECTION_DISTRIBUTION as $slug => $expected) {
            $category = Category::where('slug', $slug)->firstOrFail();
            $this->assertSame(
                $expected,
                $category->books()->count(),
                "Section {$slug} should hold {$expected} books.",
            );
        }
    }

    public function test_empty_sections_are_not_removed(): void
    {
        $this->seed([CategorySeeder::class, PublisherSeeder::class, BookSeeder::class]);

        foreach (['novels', 'early-childhood'] as $slug) {
            $category = Category::where('slug', $slug)->firstOrFail();
            $this->assertSame(0, $category->books()->count());
        }
    }

    public function test_missing_price_and_cover_are_left_null_not_invented(): void
    {
        $this->seed([CategorySeeder::class, PublisherSeeder::class, BookSeeder::class]);

        // BOOK1 has no price; BOOK10 has no cover — exactly one of each.
        $this->assertSame(1, Book::whereNull('price')->count());
        $this->assertSame(1, Book::whereNull('cover_image')->count());
    }

    public function test_payment_method_codes_match_the_order_enum(): void
    {
        $this->seed(PaymentMethodSeeder::class);

        $seeded = PaymentMethod::pluck('code')->sort()->values()->all();
        $expected = collect(self::ORDER_PAYMENT_ENUM)->sort()->values()->all();

        // Every seeded code is a valid enum value AND all enum values are seeded.
        $this->assertSame($expected, $seeded);
    }

    public function test_online_gateway_is_seeded_disabled_by_default(): void
    {
        $this->seed(PaymentMethodSeeder::class);

        // COD + the three manual transfers are enabled; online stays off until an
        // API key is configured (docs/04 §5.1).
        $this->assertTrue(PaymentMethod::where('code', 'cod')->value('is_enabled') == true);
        $this->assertTrue((bool) PaymentMethod::where('code', 'instapay')->value('is_enabled'));
        $this->assertFalse((bool) PaymentMethod::where('code', 'online_gateway')->value('is_enabled'));
    }
}
