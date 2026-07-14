<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publishers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('slug', 190)->unique();
            // Arabic-normalized copy of the name to speed up publisher search (filled via Observer/service).
            $table->string('name_normalized', 190)->nullable();
            $table->text('description')->nullable();
            $table->string('website', 255)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            // SoftDeletes so removing a publisher never breaks book FKs.
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
            $table->index('name');
            $table->index('name_normalized');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publishers');
    }
};
