<?php

namespace Tests\Feature;

use App\Models\Franchise;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Franchise Management: CRUD, tenant scoping and the wallet-balance guard.
 */
class FranchiseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Franchise ISP', 'domain' => 'franchise.test', 'slug' => 'franchise', 'status' => 'active',
        ]);
    }

    private function url(string $path): string
    {
        // ResolveTenant keys off the request Host, so a FULL url is required.
        return 'http://' . $this->tenant->domain . '/' . ltrim($path, '/');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'            => 'North Zone Franchise',
            'type'            => 'franchise',
            'commission_type' => 'percentage',
            'commission_rate' => 12.5,
            'credit_limit'    => 5000,
            'status'          => 'active',
        ], $overrides);
    }

    public function test_franchise_is_created_with_an_auto_generated_code(): void
    {
        $this->post($this->url('/franchises'), $this->payload(['code' => '']))
            ->assertRedirect($this->url('/franchises'));

        $franchise = Franchise::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('FR-0001', $franchise->code);
        $this->assertSame(12.5, $franchise->commission_rate);

        // The next one continues the sequence.
        $this->post($this->url('/franchises'), $this->payload(['name' => 'South Zone', 'code' => '']))
            ->assertRedirect();
        $this->assertSame('FR-0002', Franchise::where('name', 'South Zone')->firstOrFail()->code);
    }

    public function test_opening_balance_seeds_the_wallet_but_edit_cannot_change_it(): void
    {
        $this->post($this->url('/franchises'), $this->payload(['balance' => 2500]))
            ->assertRedirect();

        $franchise = Franchise::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame(2500.0, $franchise->balance);
        $this->assertSame(7500.0, $franchise->availableCredit()); // 2500 + 5000 credit limit

        // A crafted balance on update must be ignored (wallet is system-maintained).
        $this->put($this->url('/franchises/' . $franchise->id), $this->payload([
            'name'    => 'Renamed',
            'balance' => 999999,
        ]))->assertRedirect();

        $franchise->refresh();
        $this->assertSame('Renamed', $franchise->name);
        $this->assertSame(2500.0, $franchise->balance);
    }

    public function test_code_must_be_unique_per_tenant_only(): void
    {
        $this->post($this->url('/franchises'), $this->payload(['code' => 'FR-A']))->assertRedirect();

        // Same tenant, same code -> rejected.
        $this->post($this->url('/franchises'), $this->payload(['name' => 'Dup', 'code' => 'FR-A']))
            ->assertSessionHasErrors('code');

        // A different tenant may reuse the code.
        $other = Tenant::create([
            'name' => 'Other ISP', 'domain' => 'other-franchise.test', 'slug' => 'otherfr', 'status' => 'active',
        ]);
        $this->post('http://' . $other->domain . '/franchises', $this->payload(['code' => 'FR-A']))
            ->assertRedirect();

        $this->assertSame(2, Franchise::where('code', 'FR-A')->count());
    }

    public function test_a_franchise_cannot_be_its_own_parent(): void
    {
        $this->post($this->url('/franchises'), $this->payload())->assertRedirect();
        $franchise = Franchise::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->put($this->url('/franchises/' . $franchise->id), $this->payload([
            'parent_id' => $franchise->id,
        ]))->assertSessionHasErrors('parent_id');
    }

    public function test_a_franchise_with_sub_franchises_cannot_be_deleted(): void
    {
        $parent = Franchise::create($this->payload([
            'tenant_id' => $this->tenant->id, 'code' => 'FR-P', 'name' => 'Parent',
        ]));
        Franchise::create($this->payload([
            'tenant_id' => $this->tenant->id, 'code' => 'FR-C', 'name' => 'Child',
            'parent_id' => $parent->id,
        ]));

        $this->delete($this->url('/franchises/' . $parent->id))
            ->assertSessionHasErrors('franchise');

        $this->assertNotNull($parent->fresh());
    }

    public function test_franchises_are_scoped_to_the_current_tenant(): void
    {
        Franchise::create($this->payload(['tenant_id' => $this->tenant->id, 'code' => 'FR-1']));

        $other = Tenant::create([
            'name' => 'Other ISP', 'domain' => 'other-list.test', 'slug' => 'otherlist', 'status' => 'active',
        ]);

        $this->get('http://' . $other->domain . '/franchises')
            ->assertOk()
            ->assertViewHas('franchises', fn ($paginator) => $paginator->total() === 0);

        $this->get($this->url('/franchises'))
            ->assertOk()
            ->assertSee('FR-1')
            ->assertViewHas('franchises', fn ($paginator) => $paginator->total() === 1);
    }
}
