<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل «من غيّر ماذا ومتى» للوحة الأدمن — بلا أي حزمة جديدة (الدستور 5.6).
 *
 * ملاحظة على الجدول اليتيم `activity_log` (هجرة 2026_07_14_100030): بُني على سكيمة
 * spatie/laravel-activitylog، والحزمة غير مثبَّتة في composer.json ولا يُكتب فيه شيء.
 * لم يُلمس هنا (خارج النطاق، بند 1.2) — هذا جدول مستقل أخفّ يخدم الغرض مباشرةً.
 *
 * ‏append-only: لا `updated_at` لأن السطر لا يُعدَّل أبدًا بعد كتابته، ولا softDeletes
 * لأن حذف أثر التدقيق يناقض الغرض منه. المورد في اللوحة للقراءة فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activities', function (Blueprint $table) {
            $table->id();

            // الفاعل. nullOnDelete: حذف المستخدم لا يمحو أثره — يبقى السطر بلا فاعل
            // معروف بدل أن يختفي (وبدل أن يمنع FK حذفَ المستخدم أصلًا).
            // ‏index() صريح قبل constrained() ليعيد MySQL استخدامه للمفتاح الأجنبي
            // فلا يُنشئ فهرسًا مكرّرًا.
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();

            // subject_type + subject_id + فهرس مركّب عليهما (بند 3.2) — نفس ما يحتاجه
            // «أرِني تاريخ هذا الطلب/الكتاب» وهو الاستعلام الأشيع على هذا الجدول.
            $table->morphs('subject');

            $table->enum('event', ['created', 'updated', 'deleted', 'restored']);

            // ‏{"field": {"old": …, "new": …}} للحقول المتغيّرة فقط، بعد حجب الحسّاس.
            $table->json('changes')->nullable();

            $table->string('ip_address', 45)->nullable(); // يتّسع لـ IPv6 كاملًا.

            // فهرس على التاريخ: الترتيب الافتراضي للقائمة ومرشّح المدى الزمني.
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activities');
    }
};
