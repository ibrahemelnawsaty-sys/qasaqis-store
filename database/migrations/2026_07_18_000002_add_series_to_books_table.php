<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ربط الكتاب بسلسلة (اختياري) + ترتيب العنوان داخلها. nullOnDelete: حذف السلسلة
// نهائيًا يُفرّغ الربط فقط ولا يمسّ الكتب.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('publisher_id')
                ->constrained('series')->nullOnDelete();
            $table->unsignedInteger('series_position')->nullable()->after('series_id');

            $table->index(['series_id', 'series_position']);
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropForeign(['series_id']);
            $table->dropIndex(['series_id', 'series_position']);
            $table->dropColumn(['series_id', 'series_position']);
        });
    }
};
