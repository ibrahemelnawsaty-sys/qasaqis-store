<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EmailRecipient;
use App\Models\EmailSuppression;
use Illuminate\View\View;

/**
 * إلغاء الاشتراك من الحملات التسويقية. التوكن السرّي (48 حرفًا) في الرابط هو المفتاح
 * الوحيد — لا حاجة لجلسة أو تسجيل دخول، فيعمل من صندوق الوارد مباشرة.
 *
 *  - show (GET):  يعرض صفحة تأكيد بزرّ (تدفّق المتصفّح).
 *  - store (POST): ينفّذ الحظر. يخدم أيضًا نقرة One-Click التي يرسلها Gmail/Yahoo
 *    كـPOST مباشر إلى نفس المسار (لذلك أُعفي من CSRF في bootstrap/app.php).
 *
 * الحظر يُسجَّل على مستوى البريد (EmailSuppression) فيُستبعَد من كل حملة قادمة، لا
 * هذه فقط. لا يمسّ رسائل المعاملات (تأكيد الطلب/التحقّق).
 */
class EmailUnsubscribeController extends Controller
{
    public function show(string $token): View
    {
        $recipient = EmailRecipient::where('token', $token)->firstOrFail();

        return view('emails.unsubscribed', [
            'email' => $recipient->email,
            'token' => $token,
            'confirmed' => EmailSuppression::query()->where('email', $recipient->email)->exists(),
        ]);
    }

    public function store(string $token): View
    {
        $recipient = EmailRecipient::where('token', $token)->firstOrFail();

        EmailSuppression::firstOrCreate(
            ['email' => $recipient->email],
            ['reason' => 'unsubscribe'],
        );

        $recipient->update(['status' => 'unsubscribed']);

        return view('emails.unsubscribed', [
            'email' => $recipient->email,
            'token' => $token,
            'confirmed' => true,
        ]);
    }
}
