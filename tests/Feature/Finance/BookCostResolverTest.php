<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Book;
use App\Models\Publisher;
use App\Services\Finance\BookCostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اشتقاق تكلفة الكتاب: سعر شراء مُدخَل يتقدّم؛ وإلا تقدير من خصم دار النشر
 * (السعر × (١ − النسبة))؛ وإلا الافتراضي العام. كل الحساب bcmath (الدستور 3.5).
 *
 * HONESTY (1.3/1.5): يُشغَّل على MySQL + bcmath عبر php artisan test.
 */
final class BookCostResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): BookCostResolver
    {
        return app(BookCostResolver::class);
    }

    public function test_manual_cost_wins_and_is_not_estimated(): void
    {
        $book = Book::factory()->create(['price' => '100.00', 'cost_price' => '40.00']);

        $r = $this->resolver()->resolve($book);

        $this->assertSame('40.00', $r['amount']);
        $this->assertFalse($r['estimated']);
    }

    public function test_estimates_from_publisher_discount_when_no_manual_cost(): void
    {
        $publisher = Publisher::factory()->create(['cost_discount_percent' => '30.00']);
        $book = Book::factory()->create([
            'price' => '100.00', 'cost_price' => null, 'publisher_id' => $publisher->id,
        ]);

        $r = $this->resolver()->resolve($book);

        $this->assertSame('70.00', $r['amount']); // 100 × (1 − 0.30)
        $this->assertTrue($r['estimated']);
    }

    public function test_falls_back_to_global_default_when_publisher_has_no_percent(): void
    {
        config(['finance.default_cost_discount_percent' => 25]);
        $publisher = Publisher::factory()->create(['cost_discount_percent' => null]);
        $book = Book::factory()->create([
            'price' => '200.00', 'cost_price' => null, 'publisher_id' => $publisher->id,
        ]);

        $r = $this->resolver()->resolve($book);

        $this->assertSame('150.00', $r['amount']); // 200 × 0.75
        $this->assertTrue($r['estimated']);
    }

    public function test_discount_is_clamped_so_cost_is_never_negative(): void
    {
        $publisher = Publisher::factory()->create(['cost_discount_percent' => '150.00']); // > 100
        $book = Book::factory()->create([
            'price' => '100.00', 'cost_price' => null, 'publisher_id' => $publisher->id,
        ]);

        $r = $this->resolver()->resolve($book);

        $this->assertSame('0.00', $r['amount']); // مقيَّدة إلى ١٠٠٪ → تكلفة صفر لا سالبة
        $this->assertTrue($r['estimated']);
    }
}
