<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// قسم «ليه الأمهات بيحبونا» في الرئيسية — كان في ملف اللغة، ننقله لقاعدة البيانات
// ليصبح قابلًا للتحرير والإضافة من لوحة الأدمن. لون خلفية البطاقة يتناوب تلقائيًا
// حسب الترتيب (نفس سلوك القالب الحالي) فلا يحتاج الأدمن لاختيار لون.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('why_items', function (Blueprint $table) {
            $table->id();
            $table->string('icon', 32)->default('💛'); // إيموجي
            $table->string('title', 150);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        DB::table('why_items')->insert([
            ['icon' => '🎯', 'title' => 'مختارة بعناية تربوية', 'description' => 'كل قصة بتزرع قيمة أو مهارة، مش مجرد تسلية.', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['icon' => '🔤', 'title' => 'لغة سليمة ومشكّلة', 'description' => 'عربية صحيحة بالحركات تساعد طفلك يقرأ صح.', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['icon' => '🎨', 'title' => 'رسوم مبهجة', 'description' => 'ألوان وصور تخطف قلب الطفل وتحبّبه في القراءة.', 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['icon' => '💰', 'title' => 'أسعار في المتناول', 'description' => 'جودة عالية بأسعار مناسبة لكل بيت عربي.', 'is_active' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('why_items');
    }
};
