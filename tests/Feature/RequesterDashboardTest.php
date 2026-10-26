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

class RequesterDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_sees_own_requisitions_on_dashboard(): void
    {
        $department = Department::query()->create(['name' => 'Sales']);
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'department_id' => $department->id,
        ]);
        $department->update(['manager_id' => $manager->id]);

        $requester = User::factory()->create([
            'role' => UserRole::Requester,
            'department_id' => $department->id,
        ]);

        $other = User::factory()->create([
            'role' => UserRole::Requester,
            'department_id' => $department->id,
        ]);

        $product = Product::query()->create([
            'sku' => 'SKU-1',
            'description' => 'Test',
            'uom' => 'PCS',
            'price' => 1,
        ]);

        $mine = Requisition::query()->create([
            'number' => 'REQ-MINE',
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'requested_on' => now()->toDateString(),
            'request_type' => 'promotion',
            'purpose' => 'Mine',
            'declaration_at' => now(),
            'status' => RequisitionStatus::Draft,
        ]);
        $mine->items()->create([
            'product_id' => $product->id,
            'requested_qty' => 1,
            'uom' => 'PCS',
            'unit_price' => 1,
        ]);

        Requisition::query()->create([
            'number' => 'REQ-OTHER',
            'requester_id' => $other->id,
            'department_id' => $department->id,
            'requested_on' => now()->toDateString(),
            'request_type' => 'promotion',
            'purpose' => 'Other',
            'declaration_at' => now(),
            'status' => RequisitionStatus::Draft,
        ]);

        $this->actingAs($requester)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('myRequests', 1)
                ->where('myRequests.0.number', 'REQ-MINE')
                ->where('role', 'requester')
            );
    }
}
