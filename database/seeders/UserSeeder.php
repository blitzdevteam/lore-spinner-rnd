<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

final class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Test user with verified email and completed profile (username set). Idempotent so re-seeding updates credentials.
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'username' => 'testuser',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        User::factory()->markEmailAsVerified()->count(9)->create();
    }
}
