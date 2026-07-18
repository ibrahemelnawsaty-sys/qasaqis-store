<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// معرض شهادات العملاء (صور) في الرئيسية — كان مرقّمًا ثابتًا (1..9) في القالب. ننقله
// لقاعدة البيانات ليضيف الأدمن صورًا جديدة عبر الرفع. نبذر التسع الحالية بمساراتها
// الساكنة (public/images/reviews)، والرفوعات الجديدة تُخزَّن على قرص public.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_images', function (Blueprint $table) {
            $table->id();
            $table->string('image_path', 300);
            $table->string('alt', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $rows = [];
        for ($n = 1; $n <= 9; $n++) {
            $rows[] = [
                'image_path' => 'images/reviews/review-'.$n.'.webp',
                'alt' => null,
                'is_active' => true,
                'sort_order' => $n,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('feedback_images')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_images');
    }
};
