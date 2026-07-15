<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// age_label كان varchar(50) وهو أقصر من بعض القيم الوصفية (مثل BOOK10:
// «مراحل عمرية مختلفة (يناسب الأطفال قبل وبعد إتقان القراءة)»). نوسّعه بأمان.
// Laravel 11: ->change() يعمل أصلًا دون doctrine/dbal.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('age_label', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('age_label', 50)->nullable()->change();
        });
    }
};
