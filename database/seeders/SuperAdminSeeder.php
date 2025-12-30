<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the superadmin user.
     * 
     * Run with: php artisan db:seed --class=SuperAdminSeeder
     */
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'admin@example.com');
        $password = env('SUPERADMIN_PASSWORD', 'secret123');
        $name = env('SUPERADMIN_NAME', 'Superadmin');

        // Check if superadmin already exists
        $existing = User::where('email', $email)->first();

        if ($existing) {
            $this->command->info("Superadmin with email {$email} already exists.");

            // Update is_superadmin if not set
            if (!$existing->is_superadmin) {
                $existing->is_superadmin = true;
                $existing->save();
                $this->command->info("Updated existing user to superadmin.");
            }

            return;
        }

        // Create new superadmin
        $superadmin = User::create([
            'global_id' => (string) Str::ulid(),
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_superadmin' => true,
        ]);

        $this->command->info("Superadmin created successfully!");
        $this->command->info("Email: {$email}");
        $this->command->info("Password: {$password}");
        $this->command->warn("Please change the password after first login.");
    }
}
