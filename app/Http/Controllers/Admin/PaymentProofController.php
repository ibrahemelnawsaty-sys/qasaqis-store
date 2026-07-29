<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * يخدم إثبات الدفع اليدويّ للأدمن من القرص الخاصّ (storage/app/private) عبر مسار
 * مُصادَق مُصرَّح — بدل رابط /storage الذي يولّده temporaryUrl لقرص local، والذي
 * يتعارض على الإنتاج مع الرابط الرمزيّ public/storage (يخدمه الخادم كملف ساكن قبل
 * أن يصل لمسار لارافل) فيُعطي 404 للملف الخاصّ غير الموجود هناك.
 *
 * التفويض خادميّ عند نقطة الفعل (بند 4.4/4.5): أدمن **مُصادَق** يملك صلاحية
 * `orders.view` — نفس صلاحية رؤية الطلب الذي يظهر فيه رابط الإثبات. أقوى من الرابط
 * الموقّع وحده (الذي يفتحه أيّ حائز له خلال مهلته بلا تحقّق صلاحية). يُعرض داخل
 * المتصفّح (inline) للصور وPDF.
 */
final class PaymentProofController extends Controller
{
    public function show(Request $request, PaymentProof $proof): StreamedResponse
    {
        abort_unless($request->user()?->can('orders.view') === true, 403);

        $disk = Storage::disk('local');   // القرص الخاصّ (storage/app/private)

        abort_unless(filled($proof->file_path) && $disk->exists($proof->file_path), 404);

        // inline: يُعرض في المتصفّح لا يُنزَّل قسرًا (صورة إثبات/PDF).
        return $disk->response($proof->file_path, null, [], 'inline');
    }
}
