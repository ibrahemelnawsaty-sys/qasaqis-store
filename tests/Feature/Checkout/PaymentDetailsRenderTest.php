<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\PaymentMethod;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * عرض تعليمات الدفع (partials/payment-details): زرّ الرابط + نسخ العنوان/الرقم.
 *
 * الحرِج هنا أمران: (1) البيانات التي وضعها الأدمن نصًّا في «التعليمات» تُستخرج
 * وتظهر بالشكل الاحترافي بلا إعادة إدخال؛ (2) لا حقن HTML عبر نصّ حرّ (XSS).
 *
 * لا يمسّ قاعدة البيانات — نبني النموذج في الذاكرة ونصيّر القالب مباشرة.
 *
 * أمانة (1.3/1.5): لم تُنفَّذ هنا — لا PHP في بيئة التطوير. تُشغَّل على الاستضافة
 * بـ `php artisan test --filter=PaymentDetailsRenderTest`.
 */
final class PaymentDetailsRenderTest extends TestCase
{
    private function render(PaymentMethod $method): string
    {
        return View::make('partials.payment-details', ['method' => $method])->render();
    }

    public function test_it_extracts_link_handle_and_number_from_free_text_instructions(): void
    {
        // نفس ما وضعه الأدمن فعلًا: كل شيء نصًّا في «التعليمات».
        $method = new PaymentMethod([
            'name' => 'إنستاباي',
            'instructions' => "https://ipn.eg/S/aboyazanahmed/instapay/70tSo7\n"
                ."اضغط الرابط لارسال نقود الي\naboyazanahmed@instapay\n\n"
                ."او من خلال الرقم التالي\n01112570030",
            'account_details' => null,
        ]);

        $html = $this->render($method);

        // زرّ الدفع يفتح الرابط الصحيح.
        $this->assertStringContainsString('class="pay-cta"', $html);
        $this->assertStringContainsString('href="https://ipn.eg/S/aboyazanahmed/instapay/70tSo7"', $html);
        // العنوان والرقم قابلان للنسخ.
        $this->assertStringContainsString('aboyazanahmed@instapay', $html);
        $this->assertStringContainsString('01112570030', $html);
        $this->assertStringContainsString('class="pay-copy"', $html);
        // زرّ عام باسم الطريقة (لا «إنستاباي» ثابتة في الكود).
        $this->assertStringContainsString('ادفع عبر إنستاباي', $html);
    }

    public function test_it_prefers_structured_account_details(): void
    {
        $method = new PaymentMethod([
            'name' => 'محفظة',
            'instructions' => 'حوّل المبلغ ثم ارفع الإثبات.',
            'account_details' => [
                'رابط الدفع' => 'https://ipn.eg/S/x/instapay/abc',
                'رقم المحفظة' => '01000000000',
            ],
        ]);

        $html = $this->render($method);

        $this->assertStringContainsString('href="https://ipn.eg/S/x/instapay/abc"', $html);
        $this->assertStringContainsString('01000000000', $html);
        // التعليمات النثرية تظهر كملاحظة (حقل مستقلّ عن البيانات المنظّمة).
        $this->assertStringContainsString('حوّل المبلغ', $html);
    }

    public function test_it_does_not_render_html_from_instructions(): void
    {
        $method = new PaymentMethod([
            'name' => 'تحويل',
            'instructions' => '<script>alert(1)</script> ادفع على https://ok.example/pay',
            'account_details' => null,
        ]);

        $html = $this->render($method);

        // الوسم الخبيث مُهرَّب، والرابط الشرعي قابل للضغط.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('href="https://ok.example/pay"', $html);
    }

    public function test_it_renders_nothing_actionable_when_method_is_null(): void
    {
        $html = View::make('partials.payment-details', ['method' => null])->render();

        $this->assertStringNotContainsString('pay-cta', $html);
        $this->assertStringNotContainsString('pay-copy', $html);
    }
}
