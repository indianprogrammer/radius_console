<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Local dev seed: registers tenant(s) keyed on the dev host so the
 * ResolveTenant middleware can resolve a tenant for 127.0.0.1 / localhost.
 */
final class DevTenantSeeder extends Seeder
{
    public function run(): void
    {
        $hosts = ['127.0.0.1', 'localhost', 'tenant1.example.com'];
        foreach ($hosts as $i => $host) {
            Tenant::updateOrCreate(
                ['domain' => $host],
                ['name' => 'Dev ISP ' . ($i + 1), 'slug' => 'devisp' . ($i + 1), 'theme_default' => 'light', 'status' => 'active']
            );
        }
    }
}
