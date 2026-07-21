<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * علامة «تكلفة تقديرية» على سطر الطلب: true حين اشتُقّت unit_cost من خصم دار
 * النشر (لا من سعر شراء مُدخَل يدويًا). تتيح للوحة أن تُميّز الأرقام المقدَّرة عن
 * المؤكّدة (أمانة، الدستور 1.4). الافتراضي false — سطور بتكلفة حقيقية أو بلا تكلفة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->boolean('cost_is_estimated')->default(false)->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('cost_is_estimated');
        });
    }
};
