<?php

namespace Tests\Feature;

use App\Models\BandwidthProfile as BandwidthProfileModel;
use App\Models\Tenant;
use App\Src\Ports\RadiusClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Validates the local-first, company-scoped Bandwidth Control flow:
 *  - store() pushes to RADIUS FIRST, then writes a local mirror with the
 *    returned radius_plan_id + the current company_id.
 *  - index() lists only the current company's profiles (name + radius id).
 *  - a second company never sees the first company's profiles.
 */
class BandwidthProfileTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $companyA;
    private Tenant $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Tenant::create([
            'name' => 'Company A', 'domain' => 'company-a.test', 'slug' => 'companya', 'status' => 'active',
        ]);
        $this->companyB = Tenant::create([
            'name' => 'Company B', 'domain' => 'company-b.test', 'slug' => 'companyb', 'status' => 'active',
        ]);

        // Mock the RADIUS port so we never hit the live server.
        $this->mock(RadiusClient::class, function (Mockery\MockInterface $m) {
            $m->shouldReceive('createPlan')->andReturn(['plan' => ['id' => 12345]]);
            $m->shouldReceive('updatePlan')->andReturn(['plan' => ['id' => 12345]]);
            $m->shouldReceive('deletePlan')->andReturn(['ok' => true]);
            $m->shouldReceive('getPlan')->andReturn(['plan' => [
                'id' => 12345, 'bandwidth_download_mbps' => 50, 'bandwidth_upload_mbps' => 10,
                'vlan_id' => 100, 'interim_interval' => 30,
            ]]);
            $m->shouldReceive('listPlans')->andReturn(['count' => 1, 'plans' => [[
                'id' => 12345, 'bandwidth_download_mbps' => 50, 'bandwidth_upload_mbps' => 10,
                'vlan_id' => 100, 'interim_interval' => 30,
            ]]]);
        });
    }

    private function url(Tenant $tenant, string $path): string
    {
        // ResolveTenant keys off the request Host, so target the tenant's
        // domain with a FULL url (relative paths default to config('app.url')).
        return 'http://' . $tenant->domain . '/' . ltrim($path, '/');
    }

    public function test_store_pushes_to_radius_then_writes_local_mirror_scoped_to_company(): void
    {
        $this->post($this->url($this->companyA, '/bandwidth-profiles'), [
                'name' => 'Residential 50/10',
                'download_mbps' => 50,
                'upload_mbps' => 10,
                'vlan_id' => 100,
            ])
            ->assertRedirect();

        $row = BandwidthProfileModel::where('company_id', $this->companyA->id)->first();
        $this->assertNotNull($row, 'Local mirror must be written for the current company.');
        $this->assertSame('Residential 50/10', $row->name);
        $this->assertSame('12345', (string) $row->radius_plan_id);
        $this->assertSame(50, $row->download_mbps);
    }

    public function test_index_lists_only_current_company_profiles(): void
    {
        BandwidthProfileModel::create([
            'company_id' => $this->companyA->id,
            'name' => 'A-Only',
            'download_mbps' => 50, 'upload_mbps' => 10, 'duration_days' => 30,
            'simultaneous_use' => 1, 'radius_plan_id' => '12345',
        ]);
        BandwidthProfileModel::create([
            'company_id' => $this->companyB->id,
            'name' => 'B-Only',
            'download_mbps' => 100, 'upload_mbps' => 20, 'duration_days' => 30,
            'simultaneous_use' => 1, 'radius_plan_id' => '99999',
        ]);

        $response = $this->get($this->url($this->companyA, '/bandwidth-profiles'));
        $response->assertStatus(200);
        $response->assertSee('A-Only');
        $response->assertSee('12345');
        $response->assertDontSee('B-Only');
        $response->assertDontSee('99999');
    }

    public function test_edit_shows_local_name_with_radius_values(): void
    {
        $row = BandwidthProfileModel::create([
            'company_id' => $this->companyA->id,
            'name' => 'Friendly Name',
            'download_mbps' => 50, 'upload_mbps' => 10, 'duration_days' => 30,
            'simultaneous_use' => 1, 'radius_plan_id' => '12345',
        ]);

        $response = $this->get($this->url($this->companyA, "/bandwidth-profiles/{$row->id}/edit"));
        $response->assertStatus(200);
        $response->assertSee('Friendly Name');
    }
}
