<?php

namespace Database\Seeders;

use App\Models\Nas;
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

        // Dev NAS mirror so the app has a reference RADIUS device. Keyed on
        // (tenant_id, nas_ip) for idempotency. SRD §4.2.
        $tenant = Tenant::where('domain', '127.0.0.1')->firstOrFail();
        Nas::updateOrCreate(
            ['tenant_id' => $tenant->id, 'nas_ip' => '10.0.0.1'],
            [
                'name' => 'Dev NAS',
                'shared_secret' => 'devsecret',
                'nas_identifier' => 'dev-nas-01',
                'type' => 'mikrotik',
                'api_enabled' => true,
            ]
        );
    }
}
