<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * اختبار إرسال البريد من الخادم (M9). أكواد التحقق تعتمد كليًا على وصول البريد،
 * وهذا الأمر يثبت وصوله قبل الاعتماد عليه (الدستور: لا اعتماد مسار بريدي قبل
 * إثبات إرساله مُلاحَظًا). الاستعمال: php artisan mail:test you@example.com
 */
class SendTestMail extends Command
{
    protected $signature = 'mail:test {email : البريد المستقبِل}';

    protected $description = 'يرسل رسالة اختبار للتأكد من عمل SMTP قبل تفعيل أكواد التحقق';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $this->info("المُرسِل الحالي: ".config('mail.default'));
        $this->info("المرسَل من: ".config('mail.from.address'));
        $this->line("جارٍ الإرسال إلى {$email}…");

        try {
            Mail::raw(
                'رسالة اختبار من «قصص أطفال». إن وصلتك هذه الرسالة، فإرسال البريد يعمل وأكواد التحقق ستصل.',
                static function ($message) use ($email): void {
                    $message->to($email)->subject('اختبار البريد — قصص أطفال');
                },
            );
        } catch (\Throwable $e) {
            $this->error('فشل الإرسال: '.$e->getMessage());
            $this->warn('راجعي إعدادات MAIL_* في .env. أكواد التحقق لن تصل حتى يعمل هذا.');

            return self::FAILURE;
        }

        $this->info('تم استدعاء الإرسال بنجاح. تحقّقي من وصول الرسالة (ومجلد المزعج).');
        $this->warn('ملاحظة: النجاح هنا يعني أن الخادم قبِل التسليم، لا أن الرسالة وصلت — أكّدي بصندوق الوارد.');

        return self::SUCCESS;
    }
}
