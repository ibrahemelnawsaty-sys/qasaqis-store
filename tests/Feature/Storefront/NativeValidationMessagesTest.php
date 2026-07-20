<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * رسائل تحقّق المتصفح بالعربية (M11) — تُحقَن في كل صفحة عبر التخطيط، فتحلّ محلّ
 * رسائل المتصفح الإنجليزية («Please fill out this field»).
 *
 * HONESTY (1.3/1.5): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class NativeValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_arabic_validation_messages_are_injected_on_every_page(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        // الرسالة العربية لخانة فارغة موجودة في مصدر الصفحة (سمة data).
        $response->assertSee('لا يمكن تركها فارغة', false);
        // تُعرض كعنصر داخل الصفحة (field-err) لا كفقاعة متصفح — تظهر على الجوال.
        $response->assertSee('field-err', false);
        $response->assertSee('preventDefault', false);
    }

    public function test_the_messages_reach_the_checkout_form_page(): void
    {
        $book = Book::factory()->create([
            'price' => '150.00',
            'stock_status' => 'in_stock',
            'stock_quantity' => 5,
            'manage_stock' => true,
        ]);

        $response = $this->withSession(['cart' => [$book->id => 1]])->get(route('checkout.show'));

        $response->assertOk();
        // نفس الحقن على صفحة الدفع حيث ظهرت رسالة المتصفح الإنجليزية للمالك.
        $response->assertSee('لا يمكن تركها فارغة', false);
        // ورسالة البريد العربية متاحة للحقن.
        $response->assertSee('بريدًا إلكترونيًا صحيحًا', false);
    }
}
