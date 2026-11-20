<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Model events must stay ON while seeding: new rows get their tenant_id from
    // them (BelongsToTenant). Do not add WithoutModelEvents here.

    /**
     * Seed the application's database.
     *
     * The first super_admin is NOT seeded (no default password in code).
     * Create it with: php artisan lems:create-super-admin
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (app()->environment('local')) {
            $this->call(DemoUsersSeeder::class);
        }
    }
}
