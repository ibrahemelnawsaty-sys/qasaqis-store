<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Editable homepage/CMS blocks (hero_slider, featured_row, cta...). Content is flexible JSON.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique(); // block identifier e.g. hero_slider.
            $table->string('area', 60);           // homepage/footer...
            $table->enum('type', ['text', 'html', 'banner', 'slider', 'products_grid', 'cta', 'image']);
            $table->string('title', 200)->nullable();
            $table->json('content')->nullable(); // flexible payload per type.
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['area', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_blocks');
    }
};
