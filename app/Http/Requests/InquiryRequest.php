<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * تحقّق نموذج الاستفسار العام (استفسار/طلب جملة/سؤال منتج/شكوى).
 * قائمة بيضاء صارمة (بند 4.1): لا تُقبل إلا الحقول العامة؛ status/admin_reply/
 * assigned_to لا تُمرَّر أبدًا من العميل رغم وجودها في $fillable (تُضبط خادميًا).
 */
class InquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['contact', 'product_question', 'complaint', 'wholesale_b2b'])],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'email' => ['nullable', 'email', 'max:191'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:2000'],
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            // مصيدة سبام (honeypot): حقل مخفي يجب أن يبقى فارغًا. تُعالَج في الـ Controller.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'نوع الاستفسار',
            'name' => 'الاسم',
            'phone' => 'رقم الجوال',
            'email' => 'البريد الإلكتروني',
            'subject' => 'الموضوع',
            'message' => 'الرسالة',
        ];
    }
}
