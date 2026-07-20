<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * التحقق من مدخل كود التأكيد (M9). قائمة بيضاء صارمة: أرقام فقط بطول الكود المضبوط.
 */
class VerifyCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // المسار محميّ بحارس customer؛ الملكية من الجلسة لا من مدخل.
        return true;
    }

    protected function prepareForValidation(): void
    {
        // إزالة أي مسافات يلصقها الملء التلقائي أو النسخ.
        if (is_string($this->input('code'))) {
            $this->merge(['code' => preg_replace('/\s+/', '', (string) $this->input('code'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $length = (int) config('verification.code_length', 6);

        return [
            'code' => ['required', 'string', 'regex:/^\d{'.$length.'}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => __('account.verify.code_required'),
            'code.regex' => __('account.verify.code_format', [
                'length' => (int) config('verification.code_length', 6),
            ]),
        ];
    }
}
