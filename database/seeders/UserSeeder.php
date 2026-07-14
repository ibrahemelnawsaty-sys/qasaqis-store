<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Bootstraps at least two super_admin accounts (constitution 0.7 allows several).
 *
 * SECURITY: passwords are hashed with Hash::make (constitution 4.3) and taken from
 * SEED_SUPER_ADMIN_PASSWORD in .env when present. The fallback below is a documented
 * NON-SECRET placeholder — it is NOT a real credential. CHANGE IT immediately after
 * the first login (and prefer setting the env var before seeding on any real server).
 */
class UserSeeder extends Seeder
{
    // Documented placeholder only — override via SEED_SUPER_ADMIN_PASSWORD in .env.
    private const DEFAULT_PASSWORD = 'Qasaqis@Change-Me-2026';

    public function run(): void
    {
        $password = (string) env('SEED_SUPER_ADMIN_PASSWORD', self::DEFAULT_PASSWORD);

        $superAdmins = [
            ['name' => 'المالك',       'email' => 'owner@qasaqis.store'],
            ['name' => 'مدير النظام',   'email' => 'admin@qasaqis.store'],
        ];

        foreach ($superAdmins as $data) {
            /** @var User $user */
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($password), // hashed cast keeps it one-way.
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            if (! $user->hasRole('super_admin')) {
                $user->assignRole('super_admin');
            }
        }
    }
}
