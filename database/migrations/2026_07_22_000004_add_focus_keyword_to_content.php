<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الكلمة المفتاحية للتحليل (focus keyword، نظير Yoast) على الكتب والمقالات: العبارة
 * التي يُقيَّم المحتوى لها في محلّل المحرّر. اختيارية؛ تُحفَظ ليبقى التحليل عند العودة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('focus_keyword', 120)->nullable()->after('slug');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('focus_keyword', 120)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('focus_keyword');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('focus_keyword');
        });
    }
};
