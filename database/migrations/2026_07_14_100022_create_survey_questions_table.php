<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('question_text', 500);
            $table->enum('type', ['text', 'textarea', 'single_choice', 'multi_choice', 'rating', 'yes_no']);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['survey_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
