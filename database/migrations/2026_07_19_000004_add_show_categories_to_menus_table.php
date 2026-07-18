<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// خيار في إعدادات القائمة: إظهار روابط الأقسام تلقائيًا مع روابط القائمة أم لا.
// الافتراضي true حفاظًا على السلوك الحالي (الأقسام ظاهرة).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->boolean('show_categories')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('show_categories');
        });
    }
};
