<?php

namespace Tests\Feature;

use App\Enums\RequisitionStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequisitionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function withSignature(User $user): User
    {
        $path = 'signatures/users/user-'.$user->id.'.png';
        Storage::disk('public')->put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
        $user->update(['signature_path' => $path]);

        return $user->fresh();
    }

    public function test_happy_path_release_without_line_changes(): void
    {
        Storage::fake('public');

        $department = Department::query()->create(['name' => 'Sales']);
        $manager = $this->withSignature(User::factory()->create([
            'role' => UserRole::Manager,
            'department_id' => $department->id,
        ]));
        $department->update(['manager_id' => $manager->id]);

        $requester = $this->withSignature(User::factory()->create([
            'role' => UserRole::Requester,
            'department_id' => $department->id,
        ]));
        $inventory = $this->withSignature(User::factory()->create(['role' => UserRole::InventoryManager]));
        $warehouse = $this->withSignature(User::factory()->create(['role' => UserRole::Warehouse]));
        $product = Product::query()->create([
            'sku' => 'SKU-1',
            'description' => 'Test Product',
            'uom' => 'PCS',
            'price' => 10,
        ]);

        $this->actingAs($requester)
            ->post(route('requisitions.store'), [
                'request_type' => 'promotion',
                'purpose' => 'Demo purpose',
                'declaration' => true,
                'items' => [
                    ['product_id' => $product->id, 'qty' => 2],
                ],
                'should_submit' => true,
            ])
            ->assertRedirect();

        $requisition = Requisition::query()->first();
        $this->assertSame(RequisitionStatus::PendingManager, $requisition->status);
        $this->assertNotNull($requisition->requester_signature_path);

        $this->actingAs($manager)
            ->post(route('requisitions.manager.approve', $requisition))
            ->assertRedirect();
        $this->assertSame(RequisitionStatus::PendingInventory, $requisition->fresh()->status);
        $this->assertNotNull($requisition->fresh()->manager_signature_path);

        $this->actingAs($inventory)
            ->post(route('requisitions.inventory.approve', $requisition))
            ->assertRedirect();
        $this->assertSame(RequisitionStatus::ReadyForWarehouse, $requisition->fresh()->status);

        $item = $requisition->items()->first();
        $this->actingAs($warehouse)
            ->post(route('requisitions.warehouse.release', $requisition), [
                'lines' => [
                    [
                        'id' => $item->id,
                        'fulfilled_qty' => 2,
                        'product_id' => $product->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $released = $requisition->fresh();
        $this->assertSame(RequisitionStatus::Released, $released->status);
        $this->assertNotNull($released->warehouse_signature_path);
    }

    public function test_warehouse_changes_require_inventory_reapproval(): void
    {
        Storage::fake('public');

        $department = Department::query()->create(['name' => 'Sales']);
        $manager = $this->withSignature(User::factory()->create([
            'role' => UserRole::Manager,
            'department_id' => $department->id,
        ]));
        $department->update(['manager_id' => $manager->id]);

        $requester = $this->withSignature(User::factory()->create([
            'role' => UserRole::Requester,
            'department_id' => $department->id,
        ]));
        $inventory = $this->withSignature(User::factory()->create(['role' => UserRole::InventoryManager]));
        $warehouse = $this->withSignature(User::factory()->create(['role' => UserRole::Warehouse]));
        $product = Product::query()->create([
            'sku' => 'SKU-1',
            'description' => 'Test Product',
            'uom' => 'PCS',
            'price' => 10,
        ]);

        $this->actingAs($requester)->post(route('requisitions.store'), [
            'request_type' => 'donation',
            'purpose' => 'Charity',
            'declaration' => true,
            'items' => [['product_id' => $product->id, 'qty' => 5]],
            'should_submit' => true,
        ]);

        $requisition = Requisition::query()->first();
        $this->actingAs($manager)->post(route('requisitions.manager.approve', $requisition));
        $this->actingAs($inventory)->post(route('requisitions.inventory.approve', $requisition));

        $item = $requisition->items()->first();
        $this->actingAs($warehouse)->post(route('requisitions.warehouse.release', $requisition), [
            'lines' => [[
                'id' => $item->id,
                'fulfilled_qty' => 3,
                'product_id' => $product->id,
                'change_reason' => 'Only 3 in stock',
            ]],
        ]);

        $this->assertSame(
            RequisitionStatus::PendingInventoryChangeReview,
            $requisition->fresh()->status,
        );

        $this->actingAs($inventory)
            ->post(route('requisitions.inventory.approve-changes', $requisition))
            ->assertRedirect();

        $this->assertSame(RequisitionStatus::ReadyForWarehouse, $requisition->fresh()->status);

        $item = $requisition->fresh()->items()->first();
        $this->actingAs($warehouse)->post(route('requisitions.warehouse.release', $requisition), [
            'lines' => [[
                'id' => $item->id,
                'fulfilled_qty' => 3,
                'product_id' => $product->id,
            ]],
        ]);

        $this->assertSame(RequisitionStatus::Released, $requisition->fresh()->status);
    }
}
