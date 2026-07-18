<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// شريط المزايا/الثقة في الرئيسية — كان مكتوبًا في ملف اللغة، ننقله لقاعدة البيانات
// ليصبح قابلًا للتحرير والإضافة من لوحة الأدمن (المحتوى في DB لا في القوالب).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trust_items', function (Blueprint $table) {
            $table->id();
            $table->string('icon', 40)->default('badge-check');
            $table->string('title', 150);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // بذر العناصر الأربعة الحالية كي تظهر جاهزةً وقابلةً للتحرير بعد الترحيل مباشرة.
        DB::table('trust_items')->insert([
            ['icon' => 'globe', 'title' => 'شحن دولي لكل الدول', 'description' => 'نوصّل كتبك لأي مكان بأمان', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['icon' => 'gift', 'title' => 'تغليف هدايا مجاني', 'description' => 'كتبك تصلك بشكل يليق بها', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['icon' => 'badge-check', 'title' => 'كتب أصلية بجودة عالية', 'description' => 'إصدارات موثوقة وطباعة ممتازة', 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['icon' => 'chat', 'title' => 'دعم واتساب سريع', 'description' => 'نساعدكم في أي وقت', 'is_active' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_items');
    }
};
