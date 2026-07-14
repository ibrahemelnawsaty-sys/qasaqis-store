<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('parent_id')->nullable()
                ->constrained('menu_items')->cascadeOnDelete()->cascadeOnUpdate(); // multi-level.
            $table->string('label', 100);
            $table->string('url', 300)->nullable();
            $table->enum('link_type', ['url', 'page', 'category', 'product', 'publisher'])->default('url');
            $table->string('linkable_type', 150)->nullable(); // polymorphic target.
            $table->unsignedBigInteger('linkable_id')->nullable();
            $table->enum('target', ['_self', '_blank'])->default('_self');
            $table->string('icon', 60)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort_order']);
            $table->index(['linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
