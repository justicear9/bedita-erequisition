<?php

namespace Tests\Feature;

use App\Enums\RequisitionStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_manager_sees_dashboard_stats_and_reports(): void
    {
        $department = Department::query()->create(['name' => 'Sales']);
        $inventory = User::factory()->create(['role' => UserRole::InventoryManager]);
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
            'department_id' => $department->id,
        ]);
        $product = Product::query()->create([
            'sku' => 'SKU-1',
            'description' => 'Test',
            'uom' => 'PCS',
            'price' => 5,
        ]);

        $req = Requisition::query()->create([
            'number' => 'REQ-INV-1',
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'requested_on' => now()->toDateString(),
            'request_type' => 'promotion',
            'purpose' => 'Stats check',
            'declaration_at' => now(),
            'status' => RequisitionStatus::PendingInventory,
        ]);
        $req->items()->create([
            'product_id' => $product->id,
            'requested_qty' => 3,
            'uom' => 'PCS',
            'unit_price' => 5,
        ]);

        $this->actingAs($inventory)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('role', 'inventory_manager')
                ->has('stats')
                ->where('stats.pending_inventory', 1)
            );

        $this->actingAs($inventory)
            ->get(route('inventory.reports'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/Reports')
                ->has('summary')
                ->where('summary.total', 1)
            );

        $this->actingAs($inventory)
            ->get(route('inventory.reports.export'))
            ->assertOk();
    }

    public function test_requester_cannot_access_inventory_reports(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Requester]);

        $this->actingAs($requester)
            ->get(route('inventory.reports'))
            ->assertForbidden();
    }
}
