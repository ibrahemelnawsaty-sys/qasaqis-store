<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\InquiryRequest;
use App\Models\Inquiry;
use Illuminate\Http\RedirectResponse;

/**
 * استقبال نموذج الاستفسارات العام وحفظه في جدول inquiries ليظهر للأدمن/الدعم
 * في مورد «الاستفسارات». الحقول الإدارية (status/ip) تُضبط خادميًا لا من العميل.
 */
class InquiryController extends Controller
{
    public function store(InquiryRequest $request): RedirectResponse
    {
        // مصيدة سبام: لو ملأ بوت الحقل المخفي، نُظهر نجاحًا دون حفظ (لا نُنبّه البوت).
        if (filled($request->input('website'))) {
            return back()->with('inquiry_success', true)->withFragment('inquiry');
        }

        Inquiry::create([
            ...$request->safe()->only(['type', 'name', 'phone', 'email', 'subject', 'message', 'book_id']),
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        return back()
            ->with('inquiry_success', true)
            ->withFragment('inquiry');
    }
}
