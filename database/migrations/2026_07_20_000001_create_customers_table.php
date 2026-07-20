<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول حسابات العملاء — منفصل تمامًا عن users (جدول الإداريين).
 *
 * لماذا جدول منفصل لا توسعة لـ users (الباب 8.3):
 * users مربوط بـ Filament وspatie/permission وبـ User::canAccessPanel()، وفيه
 * email فريد NOT NULL وpassword إلزامي. خلط العملاء به يجعل كل صف عميل مرشّحًا
 * لخطأ صلاحيات واحد يفتح /admin. الفصل هنا قرار أمني لا تنظيمي.
 *
 * ملاحظات تصميمية:
 *
 * 1) phone_normalized هو **الهوية** ومعرّف الدخول الوحيد: 10 خانات مطبّعة عبر
 *    App\Support\Phone\PhoneNormalizer (1[0125] + 8 أرقام). CHAR(10) لا VARCHAR
 *    لأن الطول ثابت بحكم التطبيع. القيد الفريد هو المقصود بـ«فهرس على
 *    phone_normalized»: فهرس UNIQUE فهرسٌ كامل، وإضافة فهرس عادي فوقه على نفس
 *    العمود تكرار محض يبطئ الكتابة بلا فائدة قراءة. نفس المنطق ينطبق على email.
 *
 * 2) القيد الفريد يشمل الصفوف المحذوفة ناعمًا (softDeletes لا يعرفه محرّك
 *    قاعدة البيانات). هذا **مقصود**: حساب محذوف ناعمًا يحجز رقمه فلا يستولي
 *    عليه شخص لاحق ويرث طلبات صاحبته. مسار التسجيل يجب أن يبحث بـ withTrashed()
 *    ويعرض رسالة محايدة بدل استرجاع الصف تلقائيًا.
 *
 * 3) email و password يقبلان NULL على مستوى السكيمة بينما التسجيل يفرضهما
 *    (Form Request). السكيمة أوسع عمدًا كي لا تنكسر عند أي مسار إداري مستقبلي
 *    يُنشئ عميلًا بلا كلمة مرور؛ التضييق مكانه طبقة التحقق (الباب 2.4).
 *
 * 4) last_* ليست عنوان الشحن — هي لقطة آخر عنوان تُستعمل كقيم افتراضية لملء
 *    /checkout مسبقًا فقط. مصدر الحقيقة للعنوان يبقى صفّ الطلب نفسه.
 *
 * 5) orders_count و total_spent يُنشآن الآن ولا يُكتبان في v1. السابقة في هذا
 *    المستودع: books.avg_rating و books.reviews_count راكدان على 0 منذ إنشائهما
 *    لأن لا كود يحدّثهما. عدّاد لا يُنقَص عند الإلغاء/الاسترجاع = رقم كاذب،
 *    والحساب عند العرض من جدول orders هو المصدر الوحيد للحقيقة.
 *
 * 6) total_spent بـ decimal(10,2) لا float (الباب 3.5 / الممنوع 27).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();

            $table->string('name', 150);

            // الهوية ومعرّف الدخول — انظر الملاحظة (1).
            $table->char('phone_normalized', 10)->unique();
            $table->string('phone_e164', 20)->nullable();

            // قناة الاسترداد الوحيدة الممكنة اليوم (لا مزوّد SMS في config/services).
            $table->string('email', 191)->nullable()->unique();
            $table->string('password', 255)->nullable();

            // يبقيان NULL في v1: لا يوجد مسار تحقق من الجوال ولا من البريد.
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();

            // لقطة آخر عنوان — قيم افتراضية للملء المسبق فقط. انظر الملاحظة (4).
            $table->string('last_governorate', 50)->nullable();
            $table->string('last_city', 80)->nullable();
            $table->string('last_address_line', 300)->nullable();
            $table->char('last_country_code', 2)->nullable();

            // عدّادات مؤجَّلة — انظر الملاحظة (5).
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);

            // علامة أن الحساب استلم أول طلب سابق بربط صريح.
            $table->boolean('is_claimed')->default(false);

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        // آمن: هجرة 2026_07_20_000002 تُعكس قبل هذه (ترتيب زمني تنازلي) فتُسقط
        // المفتاح الخارجي على orders.customer_id أولًا.
        Schema::dropIfExists('customers');
    }
};
