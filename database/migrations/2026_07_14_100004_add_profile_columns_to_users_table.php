<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds project profile/scope columns to the framework's original users table
// WITHOUT editing the original 0001_01_01 users migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            // Default scope for the "support" role: restrict them to a category.
            $table->foreignId('category_id')->nullable()->after('phone')
                ->constrained('categories')->nullOnDelete()->cascadeOnUpdate();
            $table->boolean('is_active')->default(true)->after('password');
            $table->string('avatar_path', 255)->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('avatar_path');
            $table->softDeletes();

            $table->index('phone');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['phone']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'phone',
                'category_id',
                'is_active',
                'avatar_path',
                'last_login_at',
                'deleted_at',
            ]);
        });
    }
};
