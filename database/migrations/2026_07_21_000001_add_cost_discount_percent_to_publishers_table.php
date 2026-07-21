<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نسبة خصم الشراء لكل دار نشر: التكلفة الافتراضية لكتابٍ بلا سعر شراء مُدخَل =
 * السعر × (١ − النسبة/١٠٠). كل دار له نسبته؛ حين لا نسبة يُستعمل الافتراضي العام
 * (config finance.default_cost_discount_percent). nullable كي نميّز «لم تُضبط»
 * عن صفر (الدستور 1.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publishers', function (Blueprint $table): void {
            $table->decimal('cost_discount_percent', 5, 2)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('publishers', function (Blueprint $table): void {
            $table->dropColumn('cost_discount_percent');
        });
    }
};
