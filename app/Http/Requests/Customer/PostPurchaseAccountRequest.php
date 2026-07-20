<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * التحقق من نموذج إنشاء الحساب من صفحة الشكر (M10). الوصول محميّ بالتوقيع؛ هنا
 * التحقق من كلمة المرور، والبريد **فقط حين لا يحمل الطلب بريدًا** (وإلا يُؤخذ من
 * الطلب خادميًا ولا يُقبل من النموذج — بند 4.1).
 */
class PostPurchaseAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // التوقيع في وسيط المسار (signed).
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // إلزامي فقط إن كان الطلب بلا بريد؛ ومتفرّد على customers.
            'email' => [
                Rule::requiredIf(fn (): bool => blank($this->routeOrder()?->customer_email)),
                'nullable',
                'email',
                'max:191',
                Rule::unique('customers', 'email'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('account.register.email'),
            'password' => __('account.register.password'),
        ];
    }

    /** الطلب المربوط بالمسار — لتحديد هل البريد إلزامي. */
    private function routeOrder(): ?Order
    {
        $order = $this->route('order');

        return $order instanceof Order ? $order : null;
    }
}
