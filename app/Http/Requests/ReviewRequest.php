<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق نموذج إرسال رأي على كتاب (بند 2.4).
 *
 * قائمة بيضاء صارمة (بند 4.1): ثلاثة حقول فقط تُقبل من العميل. السبب ملموس لا
 * نظري: Review::$fillable يشمل status و is_verified_purchase و parent_id و
 * user_id، فتمرير `Review::create($request->validated())` كان سيسمح للعميل بنشر
 * رأيه فورًا (status=published) ومنح نفسه شارة «شراء موثّق» — تصعيد امتياز مباشر.
 * لذلك تُبنى حمولة الإدراج كاملةً في toAttributes() من قيم خادمية، ولا تُمرَّر
 * مدخلات العميل خامًا إلى الموديل أبدًا.
 *
 * قواعد التحقق مقصورة على ما هو مترجَم فعلًا في lang/ar/validation.php
 * (required, string, integer, min.numeric, min.string, max.numeric, max.string):
 * استُبدل `between:1,5` بـ `min:1|max:5` لأن مفتاح `between` غير موجود هناك،
 * وكانت الرسالة ستظهر بالإنجليزية للأم المصرية (مخالفة 6.4).
 */
class ReviewRequest extends FormRequest
{
    /** أقصى طول لنص الرأي — العمود `body` من نوع TEXT، والحد هنا سياسة لا قيد سكيمة. */
    private const MAX_BODY = 2000;

    public function authorize(): bool
    {
        // المصادقة تُفرض على المسار (حارس customer) ويعيد الـ Controller التحقق
        // منها دفاعًا في العمق؛ لا Gate/Policy هنا (قرار معماري ملزم).
        return true;
    }

    /**
     * تشذيب المسافات قبل التحقق: يمنع تجاوز `required` بمسافات فقط، ويحوّل
     * العنوان الفارغ إلى null بدل تخزين سلسلة فارغة في عمود nullable.
     */
    protected function prepareForValidation(): void
    {
        foreach (['title', 'body'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $trimmed = trim($value);
                $this->merge([$field => $trimmed === '' ? null : $trimmed]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // rating عمود unsignedTinyInteger بلا قيد CHECK في قاعدة البيانات،
            // فالمدى 1..5 يُفرض هنا حصريًا.
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:200'], // مطابق لطول العمود.
            'body' => ['required', 'string', 'min:3', 'max:'.self::MAX_BODY],
        ];
    }

    /**
     * أسماء الحقول في رسائل الخطأ — من ملف الترجمة لا مكتوبة في الكود (6.4).
     * لم تُضف إلى مصفوفة attributes في lang/ar/validation.php لأنه ملف مشترك
     * يكتبه وكيل واحد؛ التعريف المحلي هنا يؤدي الغرض بلا تصادم كتابة.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rating' => __('review.field_rating'),
            'title' => __('review.field_title'),
            'body' => __('review.field_body'),
        ];
    }

    /**
     * حمولة الإدراج الموثوقة خادميًا — على نمط CheckoutRequest::toData().
     *
     * كل قيمة حسّاسة هنا ثابتة أو ممرَّرة من الـ Controller بعد حسابها خادميًا؛
     * لا شيء منها يُقرأ من الطلب. أي حقل يرسله العميل باسم status أو
     * is_verified_purchase أو parent_id أو user_id يسقط صامتًا لأنه ليس في
     * rules() فلا يظهر في validated() ولا يصل إلى هنا.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(
        int $bookId,
        string $authorName,
        ?string $authorPhone,
        bool $isVerifiedPurchase,
    ): array {
        /** @var string|null $title */
        $title = $this->validated('title');

        return [
            'book_id' => $bookId,

            // reviews.user_id مفتاح خارجي إلى جدول users (الإداريين) — وضع معرّف
            // عميل فيه يربط الرأي بحساب أدمن يحمل نفس الرقم. يبقى null دائمًا.
            'user_id' => null,

            // الردود من صلاحيات الطاقم في Filament فقط؛ إرسال العميل دائمًا جذر.
            'parent_id' => null,

            'author_name' => $authorName,
            'author_phone' => $authorPhone,

            'rating' => (int) $this->validated('rating'),
            'title' => filled($title) ? $title : null,
            'body' => (string) $this->validated('body'),

            // لا رفع وسائط في هذا المسار.
            'has_media' => false,

            // بانتظار الاعتماد دائمًا: النشر قرار إداري، لا أثر جانبي لإرسال نموذج.
            'status' => 'pending',

            'is_verified_purchase' => $isVerifiedPurchase,
            'replied_by' => null,
        ];
    }
}
