<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete()->cascadeOnUpdate();
            // Keep the line even if the book is later deleted (historical record).
            $table->foreignId('book_id')->nullable()
                ->constrained('books')->nullOnDelete()->cascadeOnUpdate();
            $table->string('book_title', 200); // snapshot at order time.
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('line_total', 10, 2); // unit_price * quantity.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
