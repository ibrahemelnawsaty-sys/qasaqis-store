<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Book reviews plus threaded support replies (parent_id).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('parent_id')->nullable()
                ->constrained('reviews')->cascadeOnDelete()->cascadeOnUpdate(); // support reply.
            $table->string('author_name', 150);
            $table->string('author_phone', 20)->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5, null for replies.
            $table->string('title', 200)->nullable();
            $table->text('body');
            $table->boolean('has_media')->default(false);
            $table->enum('status', ['pending', 'published', 'hidden', 'spam'])->default('pending');
            $table->boolean('is_verified_purchase')->default(false);
            $table->foreignId('replied_by')->nullable()
                ->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->index(['book_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
