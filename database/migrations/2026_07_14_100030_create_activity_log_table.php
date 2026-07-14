<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit trail table matching the spatie/laravel-activitylog schema (v4).
// NOTE: the activitylog package is NOT yet in composer.json; this table is created
// standalone so admin actions can be logged now and the package dropped in later.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject'); // subject_type/subject_id (+ index).
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');   // causer_type/causer_id (+ index).
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();

            $table->index('log_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
