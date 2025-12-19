<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Tenant Database Seeder
 * 
 * This seeder runs when a new tenant database is created.
 * Add your tenant-specific seeders here.
 */
class TenantDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            // Add your tenant seeders here
            // Tenant\RoleSeeder::class,
            // Tenant\DefaultSettingsSeeder::class,
        ]);
    }
}
