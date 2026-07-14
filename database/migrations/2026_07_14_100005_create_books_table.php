<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The books catalogue (23 books). Prices are DECIMAL(10,2) NULL — never float.
// BOOK1 has no price (price NULL, may not be published); BOOK10 has no cover (cover_image NULL).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();

            // Main category — restrict delete so a category with books cannot be removed accidentally.
            $table->foreignId('category_id')
                ->constrained('categories')->restrictOnDelete()->cascadeOnUpdate();

            // Publisher — nullable; books with no visible publisher link to the default one.
            // constrained() auto-creates the index on publisher_id (do NOT add a duplicate).
            $table->foreignId('publisher_id')->nullable()
                ->constrained('publishers')->nullOnDelete()->cascadeOnUpdate();

            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('sku', 60)->nullable()->unique();

            $table->string('author', 150)->nullable();
            $table->string('illustrator', 150)->nullable();

            $table->string('short_description', 500)->nullable();
            $table->longText('long_description')->nullable(); // HTML from the editor.

            // Money — DECIMAL(10,2), NULL allowed (BOOK1 has no price yet).
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('old_price', 10, 2)->nullable();  // struck-through price for offers.
            $table->decimal('cost_price', 10, 2)->nullable(); // internal cost, hidden from support role.

            $table->integer('stock_quantity')->default(0);
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'preorder'])->default('in_stock');
            $table->boolean('manage_stock')->default(true);

            $table->unsignedTinyInteger('age_min')->nullable();
            $table->unsignedTinyInteger('age_max')->nullable();
            $table->string('age_label', 50)->nullable(); // display text e.g. "4 - 8 سنوات".

            $table->unsignedSmallInteger('pages_count')->nullable();
            $table->string('isbn', 20)->nullable();
            $table->unsignedSmallInteger('weight_grams')->nullable();

            $table->json('learning_outcomes')->nullable(); // array of learning outcomes.

            // Single cover image path (gallery lives in book_images). NULL => neutral placeholder.
            $table->string('cover_image', 255)->nullable();

            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->unsignedInteger('views_count')->default(0);

            // Denormalised for performance (updated via observers/events later).
            $table->decimal('avg_rating', 2, 1)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);

            // Arabic-normalized search fields (populated in PHP; NOT via MySQL functions).
            $table->string('title_normalized', 255)->nullable(); // prefix autocomplete (LIKE 'term%').
            $table->text('search_index')->nullable();            // unified normalized blob (FULLTEXT).

            $table->timestamps();
            $table->softDeletes();

            // Composite index serving the public listing pages in one index.
            $table->index(['is_published', 'is_featured', 'sort_order']);
            $table->index('stock_status');
            $table->index('published_at');
            $table->index('title_normalized');

            // FULLTEXT for search (InnoDB / MySQL 8): the storefront queries
            // search_index in BOOLEAN MODE; a substring LIKE is used only as a
            // fallback for tokens shorter than innodb_ft_min_token_size.
            $table->fullText(['title', 'short_description']);
            $table->fullText('search_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
