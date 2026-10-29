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

class WarehouseLotReleaseTest extends TestCase
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
        $product = Product::query()->create([
            'sku' => 'SKU-LOT',
            'description' => 'Lot Product',
            'uom' => 'PCS',
            'price' => 8,
        ]);

        $requisition = Requisition::query()->create([
            'number' => 'REQ-LOT-1',
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'requested_on' => now()->toDateString(),
            'request_type' => 'promotion',
            'purpose' => 'Lots',
            'declaration_at' => now(),
            'status' => RequisitionStatus::ReadyForWarehouse,
        ]);

        $item = $requisition->items()->create([
            'product_id' => $product->id,
            'requested_qty' => 10,
            'uom' => 'PCS',
            'unit_price' => 8,
        ]);

        return compact('requisition', 'item', 'warehouse', 'product');
    }

    public function test_lot_qty_mismatch_fails(): void
    {
        Notification::fake();
        ['requisition' => $requisition, 'item' => $item, 'warehouse' => $warehouse] = $this->readyRequisition();

        $this->actingAs($warehouse)
            ->from(route('requisitions.show', $requisition))
            ->post(route('requisitions.warehouse.release', $requisition), [
                'lines' => [[
                    'id' => $item->id,
                    'fulfilled_qty' => 10,
                    'product_id' => $item->product_id,
                    'lots' => [
                        ['lot_no' => 'A1', 'expires_on' => now()->addYear()->toDateString(), 'qty' => 4],
                        ['lot_no' => 'A2', 'expires_on' => now()->addMonths(18)->toDateString(), 'qty' => 3],
                    ],
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('lines');

        $this->assertSame(RequisitionStatus::ReadyForWarehouse, $requisition->fresh()->status);
        $this->assertDatabaseCount('requisition_item_lots', 0);
    }

    public function test_matching_multi_lots_release_successfully(): void
    {
        Notification::fake();
        ['requisition' => $requisition, 'item' => $item, 'warehouse' => $warehouse] = $this->readyRequisition();

        $this->actingAs($warehouse)
            ->post(route('requisitions.warehouse.release', $requisition), [
                'lines' => [[
                    'id' => $item->id,
                    'fulfilled_qty' => 10,
                    'product_id' => $item->product_id,
                    'lots' => [
                        ['lot_no' => 'A1', 'expires_on' => now()->addYear()->toDateString(), 'qty' => 6],
                        ['lot_no' => 'A2', 'expires_on' => now()->addMonths(18)->toDateString(), 'qty' => 4],
                    ],
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(RequisitionStatus::Released, $requisition->fresh()->status);
        $this->assertDatabaseCount('requisition_item_lots', 2);
        $this->assertEquals(10, $item->fresh()->lots()->sum('qty'));
    }

    public function test_missing_lots_fails_when_fulfilled_qty_positive(): void
    {
        Notification::fake();
        ['requisition' => $requisition, 'item' => $item, 'warehouse' => $warehouse] = $this->readyRequisition();

        $this->actingAs($warehouse)
            ->from(route('requisitions.show', $requisition))
            ->post(route('requisitions.warehouse.release', $requisition), [
                'lines' => [[
                    'id' => $item->id,
                    'fulfilled_qty' => 10,
                    'product_id' => $item->product_id,
                    'lots' => [],
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('lines');
    }
}
