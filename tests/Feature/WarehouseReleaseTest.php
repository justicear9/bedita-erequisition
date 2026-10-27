<?php

namespace Tests\Feature;

use App\Enums\RequisitionStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WarehouseReleaseTest extends TestCase
{
    use RefreshDatabase;

    private function withSignature(User $user): User
    {
        Storage::fake('public');
        $path = 'signatures/users/user-'.$user->id.'.png';
        Storage::disk('public')->put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
        $user->update(['signature_path' => $path]);

        return $user->fresh();
    }

    private function readyRequisition(): array
    {
        $department = Department::query()->create(['name' => 'Sales']);
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
            'department_id' => $department->id,
        ]);
        $warehouse = $this->withSignature(User::factory()->create(['role' => UserRole::Warehouse]));
        $inventory = $this->withSignature(User::factory()->create(['role' => UserRole::InventoryManager]));
        $product = Product::query()->create([
            'sku' => 'SKU-WH',
            'description' => 'Warehouse Product',
            'uom' => 'PCS',
            'price' => 8,
        ]);

        $requisition = Requisition::query()->create([
            'number' => 'REQ-WH-1',
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'requested_on' => now()->toDateString(),
            'request_type' => 'promotion',
            'purpose' => 'Test',
            'declaration_at' => now(),
            'status' => RequisitionStatus::ReadyForWarehouse,
        ]);

        $item = $requisition->items()->create([
            'product_id' => $product->id,
            'requested_qty' => 5,
            'uom' => 'PCS',
            'unit_price' => 8,
        ]);

        return compact('requisition', 'item', 'warehouse', 'inventory', 'product', 'requester');
    }

    public function test_warehouse_change_without_reason_fails_with_error(): void
    {
        Notification::fake();
        ['requisition' => $requisition, 'item' => $item, 'warehouse' => $warehouse] = $this->readyRequisition();

        $this->actingAs($warehouse)
            ->from(route('requisitions.show', $requisition))
            ->post(route('requisitions.warehouse.release', $requisition), [
                'lines' => [[
                    'id' => $item->id,
                    'fulfilled_qty' => 2,
                    'product_id' => $item->product_id,
                    'change_reason' => '',
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('lines')
            ->assertSessionHas('error');

        $this->assertSame(
            RequisitionStatus::ReadyForWarehouse,
            $requisition->fresh()->status,
        );
    }

    public function test_warehouse_change_with_reason_goes_to_inventory_review(): void
    {
        Notification::fake();
        [
            'requisition' => $requisition,
            'item' => $item,
            'warehouse' => $warehouse,
            'inventory' => $inventory,
        ] = $this->readyRequisition();

        $this->actingAs($warehouse)
            ->post(route('requisitions.warehouse.release', $requisition), [
                'lines' => [[
                    'id' => $item->id,
                    'fulfilled_qty' => 2,
                    'product_id' => $item->product_id,
                    'change_reason' => 'Only 2 in stock',
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            RequisitionStatus::PendingInventoryChangeReview,
            $requisition->fresh()->status,
        );

        Notification::assertSentTo($inventory, \App\Notifications\RequisitionStatusNotification::class);
    }
}
