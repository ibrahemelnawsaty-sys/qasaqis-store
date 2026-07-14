<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Polymorphic SEO for Book/Category/Page/Publisher — one SEO row per entity.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->string('seoable_type', 150);
            $table->unsignedBigInteger('seoable_id');
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('meta_keywords', 255)->nullable();
            $table->string('canonical_url', 300)->nullable();
            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 320)->nullable();
            $table->string('og_image_path', 255)->nullable();
            $table->enum('robots', [
                'index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow',
            ])->default('index,follow');
            $table->json('structured_data')->nullable(); // custom JSON-LD.
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
