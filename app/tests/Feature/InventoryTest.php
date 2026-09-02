<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inventory: CRUD, the per-tenant SKU rule, the low-stock classification that
 * drives the header cards and the filter, and tenant isolation.
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Inventory ISP', 'domain' => 'inventory.test', 'slug' => 'inventory', 'status' => 'active',
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
            'sku'            => 'RTR-001',
            'name'           => 'Dual-band Router',
            'category'       => 'physical',
            'unit'           => 'pcs',
            'stock_quantity' => 25,
            'reorder_point'  => 5,
            'cost_price'     => 1200,
            'sale_price'     => 1750,
            'is_active'      => 1,
        ], $overrides);
    }

    private function item(array $overrides = []): Inventory
    {
        return Inventory::create(array_merge(
            ['tenant_id' => $this->tenant->id],
            $this->payload($overrides),
        ));
    }

    public function test_an_item_is_created_and_scoped_to_the_tenant(): void
    {
        $this->post($this->url('/inventory'), $this->payload())
            ->assertRedirect($this->url('/inventory'));

        $item = Inventory::where('sku', 'RTR-001')->firstOrFail();
        $this->assertSame($this->tenant->id, $item->tenant_id);
        $this->assertSame('Dual-band Router', $item->name);
        $this->assertTrue($item->is_active);
        $this->assertSame(30000.0, $item->stockValue()); // 25 * 1200
    }

    public function test_required_fields_are_enforced(): void
    {
        $this->post($this->url('/inventory'), [])
            ->assertSessionHasErrors(['sku', 'name', 'category', 'stock_quantity', 'reorder_point', 'cost_price', 'sale_price']);
    }

    public function test_category_must_be_one_of_the_known_values(): void
    {
        $this->post($this->url('/inventory'), $this->payload(['category' => 'spaceship']))
            ->assertSessionHasErrors('category');
    }

    public function test_negative_quantities_and_prices_are_rejected(): void
    {
        $this->post($this->url('/inventory'), $this->payload([
            'stock_quantity' => -1,
            'cost_price'     => -5,
        ]))->assertSessionHasErrors(['stock_quantity', 'cost_price']);
    }

    public function test_sku_must_be_unique_per_tenant_only(): void
    {
        $this->post($this->url('/inventory'), $this->payload())->assertRedirect();

        // Same tenant, same SKU -> rejected.
        $this->post($this->url('/inventory'), $this->payload(['name' => 'Dup']))
            ->assertSessionHasErrors('sku');

        // A different tenant may reuse the SKU.
        $other = Tenant::create([
            'name' => 'Other ISP', 'domain' => 'other-inv.test', 'slug' => 'other-inv', 'status' => 'active',
        ]);
        $this->post('http://' . $other->domain . '/inventory', $this->payload())->assertRedirect();

        $this->assertSame(2, Inventory::where('sku', 'RTR-001')->count());
    }

    public function test_editing_an_item_keeps_its_own_sku_valid(): void
    {
        $item = $this->item();

        $this->put($this->url('/inventory/' . $item->id), $this->payload([
            'name'           => 'Renamed Router',
            'stock_quantity' => 4,
            'is_active'      => 0,
        ]))->assertRedirect($this->url('/inventory'));

        $item->refresh();
        $this->assertSame('Renamed Router', $item->name);
        $this->assertFalse($item->is_active);
        $this->assertTrue($item->isLowStock());
    }

    public function test_low_stock_covers_at_threshold_and_out_of_stock(): void
    {
        $this->assertFalse($this->item(['sku' => 'A', 'stock_quantity' => 6, 'reorder_point' => 5])->isLowStock());
        $this->assertTrue($this->item(['sku' => 'B', 'stock_quantity' => 5, 'reorder_point' => 5])->isLowStock());

        $empty = $this->item(['sku' => 'C', 'stock_quantity' => 0, 'reorder_point' => 5]);
        $this->assertTrue($empty->isLowStock());
        $this->assertTrue($empty->isOutOfStock());
    }

    public function test_the_low_stock_filter_returns_only_items_needing_restock(): void
    {
        $this->item(['sku' => 'OK', 'name' => 'Healthy Stock', 'stock_quantity' => 50, 'reorder_point' => 5]);
        $this->item(['sku' => 'LOW', 'name' => 'Needs Restock', 'stock_quantity' => 2, 'reorder_point' => 5]);

        $this->get($this->url('/inventory?low_stock=1'))
            ->assertOk()
            ->assertSee('Needs Restock')
            ->assertDontSee('Healthy Stock');
    }

    public function test_search_and_category_filters_narrow_the_list(): void
    {
        $this->item(['sku' => 'CBL-1', 'name' => 'CAT6 Cable', 'category' => 'physical']);
        $this->item(['sku' => 'LIC-1', 'name' => 'Portal License', 'category' => 'digital']);

        $this->get($this->url('/inventory?q=CBL'))
            ->assertOk()
            ->assertSee('CAT6 Cable')
            ->assertDontSee('Portal License');

        $this->get($this->url('/inventory?category=digital'))
            ->assertOk()
            ->assertSee('Portal License')
            ->assertDontSee('CAT6 Cable');
    }

    public function test_summary_cards_count_the_whole_tenant(): void
    {
        $this->item(['sku' => 'S1', 'stock_quantity' => 10, 'reorder_point' => 2, 'cost_price' => 100]);
        $this->item(['sku' => 'S2', 'stock_quantity' => 1, 'reorder_point' => 5, 'cost_price' => 50, 'is_active' => 0]);

        $this->get($this->url('/inventory'))
            ->assertOk()
            ->assertSee('Low Stock')
            ->assertSee('Stock Value (cost)')
            ->assertSee('1,050.00'); // 10*100 + 1*50
    }

    public function test_another_tenants_item_is_not_reachable(): void
    {
        $other = Tenant::create([
            'name' => 'Rival ISP', 'domain' => 'rival-inv.test', 'slug' => 'rival-inv', 'status' => 'active',
        ]);
        $foreign = Inventory::create(array_merge(
            ['tenant_id' => $other->id],
            $this->payload(['sku' => 'FOREIGN']),
        ));

        $this->get($this->url('/inventory/' . $foreign->id . '/edit'))->assertNotFound();
        $this->put($this->url('/inventory/' . $foreign->id), $this->payload())->assertNotFound();
        $this->delete($this->url('/inventory/' . $foreign->id))->assertNotFound();
    }

    public function test_an_item_is_deleted(): void
    {
        $item = $this->item();

        $this->delete($this->url('/inventory/' . $item->id))
            ->assertRedirect($this->url('/inventory'));

        $this->assertDatabaseMissing('inventory', ['id' => $item->id]);
    }

    /**
     * The index deletes over fetch(). A redirect there is replayed as a DELETE
     * against /inventory and returns 405, so ajax callers must get JSON.
     */
    public function test_an_ajax_delete_answers_with_json_not_a_redirect(): void
    {
        $item = $this->item();

        $this->deleteJson($this->url('/inventory/' . $item->id))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('inventory', ['id' => $item->id]);
    }
}