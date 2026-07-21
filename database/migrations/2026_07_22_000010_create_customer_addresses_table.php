<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دفتر عناوين العميلة (M12): عناوين مُسمّاة متعددة تُحفظ من طلباتها كي لا تُعيد
 * إدخالها. تختار عنوانًا محفوظًا في الدفع أو تضيف جديدًا، وتديرها من ملفها.
 *
 * ملاحظات تصميمية (الباب 8.3):
 * - كل عمود عنوان يطابق أو يتّسع لحدّ التحقّق في CheckoutRequest (governorate يسمح
 *   دوليًّا بـ100 بينما orders.governorate عموده 50 — نأخذ الأوسع هنا تفاديًا لفيض).
 * - phone هنا هو **جوال المستلِم لهذا العنوان** (قد يختلف عن جوال الحساب — توصيل
 *   لقريب مثلًا)، فلا نطبّعه كهوية؛ يُخزَّن كما أُدخِل مثل orders.customer_phone.
 * - is_default: عنوان واحد افتراضيّ يُملأ تلقائيًّا في الدفع. الحصر (عنوان واحد
 *   افتراضيّ لكل عميلة) يُطبَّق في التطبيق لا بقيد DB (أبسط وأمرن).
 * - cascadeOnDelete: يمحو الدفتر عند الحذف **الصلب** للحساب فقط. حساب العميلة
 *   يُحذف ناعمًا (SoftDeletes) فلا يُطلق هذا القيد؛ يبقى الدفتر مربوطًا بالصفّ
 *   المحذوف ناعمًا (يُستعاد معه)، ولا يحوي أكثر ممّا في orders أصلًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('label', 60);            // اسم العنوان: «المنزل»، «العمل»…
            $table->string('name', 150);            // اسم المستلِم
            $table->string('phone', 20);
            $table->string('phone_alt', 20)->nullable();
            $table->string('country_code', 2)->default('EG');
            $table->string('governorate', 100)->nullable();
            $table->string('state_province', 100)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('address_line', 300);
            $table->string('address_notes', 300)->nullable();
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            // الاستعلام الوحيد: عناوين عميلة واحدة، الافتراضيّ أولًا ثم الأحدث.
            $table->index(['customer_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
