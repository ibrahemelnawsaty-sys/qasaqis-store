<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Models\Book;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ظهور نموذج الرأي في صفحة الكتاب (M12): المنطق والمتحكّم واللغة كانت كاملة، لكن
 * الجزئية review-form لم تكن مُضمَّنة في books/show — فلم تُعرض للعميلة أي وسيلة
 * لإضافة رأيها. هذا الاختبار يحرس الربط: العميلة المسجّلة ترى النموذج، والزائرة
 * ترى دعوة الدخول.
 *
 * HONESTY (1.3/1.5): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class ReviewFormVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_logged_in_customer_sees_the_review_form_on_the_book_page(): void
    {
        $customer = Customer::factory()->create();
        $book = Book::factory()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('books.show', ['book' => $book->slug]));

        $response->assertOk();
        $response->assertSee('id="review-form"', false);                 // الجزئية مُدرَجة
        $response->assertSee(__('review.form_title'), false);            // «شاركينا رأيك…»
        $response->assertSee('name="rating"', false);                    // حقل التقييم (نموذج فعلي)
        $response->assertSee(__('review.submit'), false);                // زر «إرسال الرأي»
        $response->assertSee(route('books.reviews.store', ['book' => $book->slug]), false); // وجهة الإرسال
    }

    public function test_a_guest_sees_a_login_prompt_instead_of_the_form(): void
    {
        $book = Book::factory()->create();

        $response = $this->get(route('books.show', ['book' => $book->slug]));

        $response->assertOk();
        $response->assertSee(__('review.login_prompt'), false);   // دعوة تسجيل الدخول
        $response->assertSee(__('review.login_cta'), false);      // زر الدخول
        $response->assertDontSee('name="rating"', false);         // لا نموذج فعليّ للزائرة
    }
}
