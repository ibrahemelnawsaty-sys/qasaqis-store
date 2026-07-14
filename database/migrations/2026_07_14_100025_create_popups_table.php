<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popups', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->enum('type', ['promo', 'survey', 'newsletter', 'announcement']);
            $table->longText('content')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->foreignId('survey_id')->nullable()
                ->constrained('surveys')->nullOnDelete()->cascadeOnUpdate();
            $table->string('cta_label', 80)->nullable();
            $table->string('cta_url', 300)->nullable();
            $table->enum('display_trigger', ['on_load', 'on_exit', 'on_scroll', 'after_delay'])->default('on_load');
            $table->unsignedSmallInteger('delay_seconds')->nullable();
            $table->enum('display_frequency', ['once', 'once_per_session', 'always'])->default('once_per_session');
            $table->json('target_pages')->nullable();
            $table->json('target_devices')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popups');
    }
};
