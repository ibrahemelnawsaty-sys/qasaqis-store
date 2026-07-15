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
     *  payment methods          ->  standalone (no FK; checkout reads them by code)
     *  pages                    ->  menus (footer/header items link to pages)
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            PublisherSeeder::class,
            BookSeeder::class,
            // مقالات المدونة تُربط بالكتب عبر article_book، فتأتي بعد BookSeeder.
            ArticleSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
            PaymentMethodSeeder::class,
            // CMS content baseline. PageSeeder must precede MenuSeeder because
            // menu items reference the seeded pages by polymorphic linkable.
            PageSeeder::class,
            HomepageBlockSeeder::class,
            MenuSeeder::class,
        ]);
    }
}
