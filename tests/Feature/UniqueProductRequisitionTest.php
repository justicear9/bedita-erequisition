<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniqueProductRequisitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_product_ids_are_rejected_on_store(): void
    {
        $department = Department::query()->create(['name' => 'Sales']);
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
            'department_id' => $department->id,
        ]);
        $product = Product::query()->create([
            'sku' => 'SKU-DUP',
            'description' => 'Duplicate Product',
            'uom' => 'PCS',
            'price' => 4,
        ]);

        $this->actingAs($requester)
            ->post(route('requisitions.store'), [
                'requested_on' => now()->toDateString(),
                'request_type' => 'promotion',
                'purpose' => 'Should fail distinct',
                'declaration' => false,
                'items' => [
                    ['product_id' => $product->id, 'qty' => 2],
                    ['product_id' => $product->id, 'qty' => 3],
                ],
                'should_submit' => false,
            ])
            ->assertSessionHasErrors('items.0.product_id');

        $this->assertDatabaseCount('requisitions', 0);
    }

    public function test_unique_products_store_successfully(): void
    {
        $department = Department::query()->create(['name' => 'Sales']);
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
            'department_id' => $department->id,
        ]);
        $a = Product::query()->create([
            'sku' => 'SKU-A',
            'description' => 'Product A',
            'uom' => 'PCS',
            'price' => 4,
        ]);
        $b = Product::query()->create([
            'sku' => 'SKU-B',
            'description' => 'Product B',
            'uom' => 'PCS',
            'price' => 5,
        ]);

        $this->actingAs($requester)
            ->post(route('requisitions.store'), [
                'requested_on' => now()->toDateString(),
                'request_type' => 'promotion',
                'purpose' => 'Two unique lines',
                'declaration' => false,
                'items' => [
                    ['product_id' => $a->id, 'qty' => 2],
                    ['product_id' => $b->id, 'qty' => 3],
                ],
                'should_submit' => false,
            ])
            ->assertRedirect();

        $requisition = Requisition::query()->first();
        $this->assertNotNull($requisition);
        $this->assertCount(2, $requisition->items);
    }
}
