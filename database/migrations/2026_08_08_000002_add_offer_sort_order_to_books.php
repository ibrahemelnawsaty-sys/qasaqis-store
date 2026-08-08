<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ترتيب مستقلّ لصفحة العروض (/offers) بالسحب — منفصل عن sort_order (تثبيت /books).
// offer_sort_order = 0 يعني «غير مرتَّب» (يُذيَّل بالأحدث)، و> 0 يعني رتبته في العروض.
// يُدار من صفحة أدمن «ترتيب العروض» (سحب/كتابة رقم) على الكتب المخصومة (old_price).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->unsignedInteger('offer_sort_order')->default(0)->after('sort_order')->index();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropColumn('offer_sort_order');
        });
    }
};
