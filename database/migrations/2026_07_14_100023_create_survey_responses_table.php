<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('respondent_name', 150)->nullable();
            $table->string('respondent_phone', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
