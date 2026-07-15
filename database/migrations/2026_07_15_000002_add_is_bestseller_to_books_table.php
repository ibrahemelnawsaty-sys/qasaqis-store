<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds the «الأكثر مبيعًا» (bestseller) flag to the books catalogue.
// Boolean, default false. A dedicated index serves the homepage query
// (WHERE is_bestseller = 1) per constitution 3.2. Touches no other column.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->boolean('is_bestseller')->default(false)->after('is_featured');
            $table->index('is_bestseller');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['is_bestseller']);
            $table->dropColumn('is_bestseller');
        });
    }
};
