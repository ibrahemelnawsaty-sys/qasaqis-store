<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order respects dependencies:
     *  categories & publishers  ->  books (FK category_id/publisher_id)
     *  roles/permissions        ->  users (super_admin role assignment)
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            PublisherSeeder::class,
            BookSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
