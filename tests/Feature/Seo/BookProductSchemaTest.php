<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\Book;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PublisherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * سكيما المنتج للكتاب: hasMerchantReturnPolicy (لا استرجاع، يطابق الصفحة المرئية) دائمًا،
 * وshippingDetails فقط حين تُضبط تكلفة شحن مصر > 0 — يُنهي تحذيرَي Merchant listings في
 * Search Console دون إعلان قيمة شحن مخترعة.
 */
class BookProductSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CategorySeeder::class, PublisherSeeder::class]);
        Http::fake();
    }

    private function pricedBook(): Book
    {
        return Book::factory()->create([
            'is_published' => true,
            'price' => '75.00',
            'stock_status' => 'in_stock',
        ]);
    }

    public function test_return_policy_is_always_emitted_in_offers(): void
    {
        $this->get(route('books.show', $this->pricedBook()))
            ->assertOk()
            ->assertSee('hasMerchantReturnPolicy')
            ->assertSee('MerchantReturnNotPermitted');
    }

    public function test_shipping_details_emitted_when_flat_cost_is_set(): void
    {
        config(['egypt.shipping.flat' => '50.00']);

        $this->get(route('books.show', $this->pricedBook()))
            ->assertOk()
            ->assertSee('OfferShippingDetails')
            ->assertSee('MonetaryAmount')
            ->assertSee('50.00');
    }

    public function test_shipping_details_omitted_when_flat_cost_is_zero(): void
    {
        config(['egypt.shipping.flat' => '0.00']);

        $this->get(route('books.show', $this->pricedBook()))
            ->assertOk()
            ->assertDontSee('OfferShippingDetails')  // لا نُعلن شحنًا بلا سعر حقيقي
            ->assertSee('MerchantReturnNotPermitted'); // سياسة الاسترجاع تبقى
    }
}
