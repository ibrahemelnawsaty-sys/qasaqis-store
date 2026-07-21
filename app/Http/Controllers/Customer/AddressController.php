<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * إدارة دفتر عناوين العميلة من ملفها: تعيين الافتراضيّ، إعادة التسمية، وحذف عنوان.
 * الإضافة تتمّ تلقائيًّا من الدفع (RememberCheckoutAddress). المسارات خلف حارس customer.
 *
 * التفويض خادميّ عند نقطة الفعل: العنوان يجب أن يخصّ العميلة المصادَقة، وإلا 404
 * (لا 403 كي لا نؤكّد وجود عنوان لمن يخمّن المعرّفات) — بند 4.4.
 */
final class AddressController extends Controller
{
    public function setDefault(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = $this->ensureOwner($request, $address);

        // عنوان افتراضيّ واحد: نُلغيه عن الكلّ ثم نُثبّت المختار — في معاملة كي لا
        // تبقى العميلة بلا افتراضيّ إن فشل السطر الثاني.
        DB::transaction(function () use ($customer, $address): void {
            $customer->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return back()->with('status', __('account.address.set_default_done'));
    }

    /** إعادة تسمية عنوان (الاسم فقط، لا العنوان نفسه) — كي تميّز الأم عناوينها. */
    public function rename(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->ensureOwner($request, $address);

        $validated = $request->validate(
            ['label' => ['required', 'string', 'max:60']],
            [],
            ['label' => __('account.address.label_field')],
        );

        $address->update(['label' => $validated['label']]);

        return back()->with('status', __('account.address.renamed'));
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $customer = $this->ensureOwner($request, $address);
        $wasDefault = $address->is_default;

        // الحذف وترقية بديل في معاملة واحدة: لا يبقى دفترٌ بلا افتراضيّ.
        DB::transaction(function () use ($customer, $address, $wasDefault): void {
            $address->delete();

            // إن حُذف الافتراضيّ، نُرقّي الأحدث (العلاقة مرتّبة is_default ثم id تنازليًّا).
            if ($wasDefault) {
                $customer->addresses()->first()?->update(['is_default' => true]);
            }
        });

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
