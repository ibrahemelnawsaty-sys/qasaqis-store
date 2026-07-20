<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أكواد التحقق (M9) — تأكيد ملكية قناة (البريد الآن، الجوال لاحقًا) بكود.
 *
 * الكود يُخزَّن **مُجزّأً** (Hash::make) لا نصًّا: تسريب الجدول لا يكشف الأكواد
 * الحيّة. identifier عام (بريد أو جوال) وchannel/purpose يميّزان الاستخدام،
 * فيخدم الجدول تأكيد البريد اليوم وOTP الجوال لاحقًا بلا تغيير سكيمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier', 191);      // البريد أو الجوال المطبَّع.
            $table->string('channel', 20);          // email · sms · whatsapp.
            $table->string('purpose', 40);          // email_verification …
            $table->string('code_hash');            // Hash::make للكود — لا نصّ.
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            // استعلام الكشف: أحدث كود حيّ لهوية وغرض معيّنين.
            $table->index(['identifier', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};
