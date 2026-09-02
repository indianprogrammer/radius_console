<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(DevTenantSeeder::class);

        $tenant = Tenant::where('domain', '127.0.0.1')->firstOrFail();

        // Local deployment account. Override these with environment variables
        // before seeding a shared environment; never reuse this password there.
        User::updateOrCreate(['email' => env('SUPERADMIN_EMAIL', 'superadmin@radius.local')], [
            'tenant_id' => $tenant->id,
            'name' => 'Super Admin',
            'username' => env('SUPERADMIN_USERNAME', 'superadmin'),
            'password' => env('SUPERADMIN_PASSWORD', 'ChangeMe!2026'),
            'role' => 'superadmin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
