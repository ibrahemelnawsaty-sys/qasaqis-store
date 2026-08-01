<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds the «جديد» (new arrival) manual flag to the books catalogue. Boolean,
// default false. The effective «new» state is HYBRID: this manual flag OR a book
// published within the last N days (N = new_badge_days setting, default 30) — see
// Book::scopeNewArrivals / newBadgeVisible. A dedicated index serves the manual
// branch of the «new» filter (WHERE is_new = 1) per constitution 3.2; the auto
// branch uses the existing published_at index. Touches no other column.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->boolean('is_new')->default(false)->after('is_bestseller');
            $table->index('is_new');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['is_new']);
            $table->dropColumn('is_new');
        });
    }
};
