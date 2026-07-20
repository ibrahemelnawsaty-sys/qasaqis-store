<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Http\Controllers\Customer\PostPurchaseAccountController;
use App\Models\Customer;
use App\Models\Order;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * إعادة تصميم شاشات الرحلة (M12): خانات رمز التحقق، مقياس قوة كلمة المرور، قسم
 * كلمة المرور القابل للطي في الملف، وإصلاح توكن خلفية البريد في بوب-اب ما بعد
 * الشراء. تحسينات تدريجية — الأساس يعمل بلا JS — والاختبار على HTML المُرسَل.
 *
 * HONESTY (1.3/1.5): تعمل عبر php artisan test (MariaDB محليًا).
 */
final class AccountScreensRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_verify_page_renders_segmented_otp_cells_over_one_real_input(): void
    {
        $customer = Customer::factory()->create(['email_verified_at' => null]);

        $response = $this->actingAs($customer, 'customer')->get(route('customer.verify.show'));

        $response->assertOk();
        $response->assertSee('otp-input', false);   // الحقل الحقيقي الواحد (يحفظ الملء التلقائي)
        $response->assertSee('otp-cells', false);    // الخانات المرئية فوقه
        $response->assertSee('one-time-code', false); // ملء الرمز التلقائي محفوظ
    }

    public function test_the_reset_page_shows_a_local_password_strength_meter(): void
    {
        $response = $this->get(route('customer.password.reset', ['token' => 'dummy-token', 'email' => 'um@example.com']));

        $response->assertOk();
        $response->assertSee('acc-strength', false);
        $response->assertSee('acc-eye', false);                 // زر إظهار كلمة المرور
        // شريط القوة الثلاثي المستويات (بنية ASCII ثابتة؛ نصّ التسمية عبر @js يُرمَّز
        // إلى \u في المصدر ويُفكّه Alpine وقت التشغيل، فلا نؤكّد النص الحرفي).
        $response->assertSee('lvl === 3', false);
    }

    public function test_the_register_page_has_an_eye_toggle_and_strength_meter(): void
    {
        $response = $this->get(route('customer.register.show'));

        $response->assertOk();
        $response->assertSee('acc-eye', false);
        $response->assertSee('acc-strength', false);
    }

    public function test_the_profile_password_section_is_collapsible(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')->get(route('customer.profile.edit'));

        $response->assertOk();
        $response->assertSee('acc-disc', false);                 // قسم قابل للطي
        $response->assertSee('<details', false);                 // عنصر أصلي — صفر JS
    }

    public function test_the_collapsible_password_section_auto_opens_when_it_has_an_error(): void
    {
        $customer = Customer::factory()->create();

        // كلمة مرور جديدة بكلمة حالية خاطئة → خطأ على current_password → يجب أن يُفتح
        // القسم تلقائيًا (open) كي ترى العميلة الخطأ بلا بحث.
        $response = $this->actingAs($customer, 'customer')
            ->from(route('customer.profile.edit'))
            ->followingRedirects()
            ->put(route('customer.profile.update'), [
                'name' => $customer->name,
                'email' => $customer->email ?? 'um@example.com',
                'current_password' => 'definitely-wrong',
                'password' => 'NewStrongPass9!',
                'password_confirmation' => 'NewStrongPass9!',
            ]);

        $response->assertOk();
        $this->assertMatchesRegularExpression('/<details[^>]*\bopen\b/', $response->getContent());
    }

    public function test_the_post_purchase_popup_uses_a_defined_surface_token(): void
    {
        // بوب-اب المشترية: طلب ضيف غير مربوط + مفتاح جلسة الشراء المطابق.
        $order = OrderFactory::new()->create([
            'customer_id' => null,
            'customer_email' => 'um@example.com',
        ]);

        $response = $this->withSession([PostPurchaseAccountController::SESSION_KEY => $order->id])
            ->get(URL::signedRoute('orders.thankyou', ['order' => $order->id]));

        $response->assertOk();
        $response->assertSee('var(--surface-soft)', false); // توكن معرَّف فعلًا
        $response->assertDontSee('surface-sub', false);     // لا أثر للتوكن المكسور
    }
}
