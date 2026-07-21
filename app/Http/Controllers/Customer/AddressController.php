<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * إدارة دفتر عناوين العميلة من ملفها: تعيين الافتراضيّ وحذف عنوان. الإضافة تتمّ
 * تلقائيًّا من الدفع (RememberCheckoutAddress). المسارات خلف حارس customer.
 *
 * التفويض خادميّ عند نقطة الفعل: العنوان يجب أن يخصّ العميلة المصادَقة، وإلا 404
 * (لا 403 كي لا نؤكّد وجود عنوان لمن يخمّن المعرّفات) — بند 4.4.
 */
final class AddressController extends Controller
{
    public function setDefault(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = $this->ensureOwner($request, $address);

        // عنوان افتراضيّ واحد: نُلغيه عن الكلّ ثم نُثبّت المختار.
        $customer->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('status', __('account.address.set_default_done'));
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = $this->ensureOwner($request, $address);
        $wasDefault = $address->is_default;

        $address->delete();

        // إن حُذف الافتراضيّ، نُرقّي الأحدث كي يبقى عنوان جاهز للملء.
        if ($wasDefault) {
            $customer->addresses()->first()?->update(['is_default' => true]);
        }

        return back()->with('status', __('account.address.deleted'));
    }

    /** يتأكّد أن العنوان يخصّ العميلة المصادَقة، ويعيدها؛ وإلا 404. */
    private function ensureOwner(Request $request, CustomerAddress $address): Customer
    {
        $customer = $request->user('customer');

        abort_unless($customer instanceof Customer && $address->customer_id === $customer->getKey(), 404);

        return $customer;
    }
}
