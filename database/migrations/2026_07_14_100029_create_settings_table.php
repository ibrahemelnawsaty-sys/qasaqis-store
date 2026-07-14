<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Generic key/value settings. Sensitive payment keys use type=encrypted (Laravel Crypt),
// never stored as plain text.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->default('general'); // general/contact/payment/shipping/social.
            $table->string('key', 100)->unique();
            $table->longText('value')->nullable();
            $table->enum('type', ['string', 'text', 'boolean', 'integer', 'json', 'encrypted'])->default('string');
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('autoload')->default(true);
            $table->timestamps();

            $table->index('group');
            $table->index('autoload');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
