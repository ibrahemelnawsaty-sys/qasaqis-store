<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// نقش خلفية يختاره الأدمن لكل صفحة CMS ولكل كتلة في الرئيسية (الدستور 0.8).
// null = «اتبع الافتراضي» (نقش الصفحة)، بينما 'none' اختيار صريح بلا نقش —
// والتمييز بينهما مقصود، لذا العمود nullable بلا قيمة افتراضية.
// لا فهرس: العمود عرضٌ فقط ولا يُستعمل في WHERE/ORDER BY (الدستور 3.2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('background_pattern', 40)->nullable()->after('template');
        });

        Schema::table('homepage_blocks', function (Blueprint $table) {
            $table->string('background_pattern', 40)->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('background_pattern');
        });

        Schema::table('homepage_blocks', function (Blueprint $table) {
            $table->dropColumn('background_pattern');
        });
    }
};
