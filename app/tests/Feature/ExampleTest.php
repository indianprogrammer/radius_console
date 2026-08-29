<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The app is multi-tenant: every request resolves a tenant from the Host
     * header, so we seed one and hit its domain.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $tenant = Tenant::create([
            'name' => 'Example ISP', 'domain' => 'example.test', 'slug' => 'example', 'status' => 'active',
        ]);

        $response = $this->get('http://example.test/');

        $response->assertStatus(200);
    }
}
