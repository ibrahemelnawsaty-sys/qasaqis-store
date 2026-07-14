<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['contact', 'product_question', 'complaint', 'wholesale_b2b'])->default('contact');
            $table->foreignId('book_id')->nullable()
                ->constrained('books')->nullOnDelete()->cascadeOnUpdate();
            $table->string('name', 150);
            $table->string('phone', 20);
            $table->string('email', 191)->nullable();
            $table->string('subject', 200)->nullable();
            $table->text('message');
            $table->enum('status', ['new', 'in_progress', 'answered', 'closed'])->default('new');
            $table->foreignId('assigned_to')->nullable()
                ->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->text('admin_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
