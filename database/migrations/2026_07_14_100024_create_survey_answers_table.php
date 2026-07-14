<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')->constrained('survey_responses')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('survey_question_id')->constrained('survey_questions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->text('answer_text')->nullable();
            $table->json('answer_options')->nullable();
            $table->tinyInteger('rating_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
    }
};
