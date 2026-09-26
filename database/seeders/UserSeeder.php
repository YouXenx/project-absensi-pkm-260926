<?php

namespace Database\Seeders;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Default admin, three active teachers (guru1..guru3@absensi.test) and one
     * deactivated teacher (guru.nonaktif@absensi.test) for testing the login block.
     * Every seeded account uses the password "password". Safe to run more than once.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@absensi.test'],
            [
                'name' => 'Administrator',
                'role' => UserRole::Admin,
                'is_active' => true,
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        foreach (range(1, 3) as $number) {
            User::firstOrCreate(
                ['email' => "guru{$number}@absensi.test"],
                [
                    'name' => fake('id_ID')->name(),
                    'role' => UserRole::Guru,
                    'is_active' => true,
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );
        }

        User::firstOrCreate(
            ['email' => 'guru.nonaktif@absensi.test'],
            [
                'name' => fake('id_ID')->name(),
                'role' => UserRole::Guru,
                'is_active' => false,
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
    }
}
