<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق نموذج «تتبّع الطلب» للضيف (M3). قائمة بيضاء صارمة (بند 2.4): رقم الطلب
 * بصيغة QSQ-YYYY-XXXXXX ورقم جوال مصري. الرسائل عربية من الترجمة. لا يكشف هذا
 * التحقق وجود الطلب — المطابقة والرد الموحّد يجريان في المتحكم/الـ Action.
 */
class TrackOrderRequest extends FormRequest
{
    /** نفس نمط الهاتف المصري في CheckoutRequest. */
    private const EGYPT_PHONE_REGEX = '/^(?:\+?20|0)?1[0125]\d{8}$/';

    /** صيغة رقم الطلب المولّدة في PlaceOrderAction::generateOrderNumber. */
    private const ORDER_NUMBER_REGEX = '/^QSQ-\d{4}-[A-Z0-9]{6}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * توحيد المدخلات قبل التحقق: رقم الطلب أحرف كبيرة + قصّ الفراغات (المخزَّن
     * بأحرف كبيرة)، والجوال قصّ الفراغات. حارس is_string يمنع تحذير
     * «Array to string conversion» عند إرسال order_number[]=x عمدًا؛ تُترك القيمة
     * غير النصّية كما هي لترفضها قاعدة 'string'.
     */
    protected function prepareForValidation(): void
    {
        $orderNumber = $this->input('order_number');
        $phone = $this->input('phone');

        $this->merge([
            'order_number' => is_string($orderNumber) ? strtoupper(trim($orderNumber)) : $orderNumber,
            'phone' => is_string($phone) ? trim($phone) : $phone,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:20', 'regex:'.self::ORDER_NUMBER_REGEX],
            'phone' => ['required', 'string', 'regex:'.self::EGYPT_PHONE_REGEX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_number.regex' => __('payment.track.invalid_number'),
            'phone.regex' => __('validation.egypt_phone'),
        ];
    }
}
