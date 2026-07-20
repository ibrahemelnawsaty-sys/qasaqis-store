<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ProfileUpdateRequest;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * ‏«بياناتي» (customer.profile.edit → GET /account/profile،
 * ‏customer.profile.update → PUT /account/profile).
 *
 * ‏الجوال يُعرض للقراءة فقط ولا يُقبل تعديله — هو هوية الدخول. التبرير الكامل في
 * ‏ProfileUpdateRequest.
 */
final class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('customer.profile', [
            'customer' => $this->customer($request),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $customer = $this->customer($request);

        // ‏forceFill لا fill: المصفوفة قائمة مغلقة بُنيت خادميًا من validated()، فلا
        // ‏تتسرّب منها مفاتيح من العميل، ولا نعتمد على محتوى $fillable في موديل
        // ‏Customer (ملك وكيل آخر) — لو نقص منه last_* لصمت fill عن الحفظ.
        $customer->forceFill($request->profileAttributes());

        $password = $request->validated('password');

        if (filled($password)) {
            // ‏Hash::make صراحةً (الدستور 4.3). آمن حتى لو أضاف الموديل cast 'hashed':
            // ‏تحقّقت من مصدر الإطار أن الـ cast يتخطّى القيم المجزّأة سلفًا
            // ‏(Hash::isHashed) فلا تجزئة مزدوجة.
            $customer->forceFill(['password' => Hash::make((string) $password)]);
        }

        $customer->save();

        // ‏تسجيل العمليات الحساسة (الدستور 4.7) — بلا كلمة المرور وبلا البريد.
        if (filled($password)) {
            Log::info('customer.password_changed', [
                'customer_id' => $customer->getKey(),
                'ip' => $request->ip(),
            ]);
        }

        return redirect()
            ->route('customer.profile.edit')
            ->with('status', __('account.profile.updated'));
    }

    /**
     * ‏خط دفاع ثانٍ فقط: البوابة الحقيقية هي middleware الحارس على مجموعة المسارات.
     */
    private function customer(Request $request): Customer
    {
        $customer = $request->user('customer');

        if (! $customer instanceof Customer) {
            abort(403);
        }

        return $customer;
    }
}
