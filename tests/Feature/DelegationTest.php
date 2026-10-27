<?php

namespace Tests\Feature;

use App\Enums\RequisitionStatus;
use App\Enums\UserRole;
use App\Models\ApprovalDelegation;
use App\Models\Department;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DelegationTest extends TestCase
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

    public function test_active_delegate_can_manager_approve(): void
    {
        Notification::fake();

        $department = Department::query()->create(['name' => 'Sales']);
        $manager = $this->withSignature(User::factory()->create([
            'role' => UserRole::Manager,
            'department_id' => $department->id,
        ]));
        $delegate = $this->withSignature(User::factory()->create([
            'role' => UserRole::Manager,
            'department_id' => $department->id,
        ]));
        $department->update(['manager_id' => $manager->id]);

        $requester = User::factory()->create([
            'role' => UserRole::Requester,
            'department_id' => $department->id,
        ]);

        ApprovalDelegation::query()->create([
            'delegator_id' => $manager->id,
            'delegate_id' => $delegate->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'active' => true,
        ]);

        $requisition = Requisition::query()->create([
            'number' => 'REQ-DEL-1',
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'requested_on' => now()->toDateString(),
            'request_type' => 'promotion',
            'purpose' => 'Delegate test',
            'declaration_at' => now(),
            'status' => RequisitionStatus::PendingManager,
        ]);

        $this->actingAs($delegate)
            ->post(route('requisitions.manager.approve', $requisition))
            ->assertRedirect();

        $this->assertSame(
            RequisitionStatus::PendingInventory,
            $requisition->fresh()->status,
        );
    }

    public function test_inactive_delegate_cannot_manager_approve(): void
    {
        $department = Department::query()->create(['name' => 'Sales']);
        $manager = $this->withSignature(User::factory()->create([
            'role' => UserRole::Manager,
            'department_id' => $department->id,
        ]));
        $delegate = $this->withSignature(User::factory()->create([
            'role' => UserRole::Manager,
            'department_id' => $department->id,
        ]));
        $department->update(['manager_id' => $manager->id]);

        $requester = User::factory()->create([
            'role' => UserRole::Requester,
            'department_id' => $department->id,
        ]);

        ApprovalDelegation::query()->create([
            'delegator_id' => $manager->id,
            'delegate_id' => $delegate->id,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
            'active' => true,
        ]);

        $requisition = Requisition::query()->create([
            'number' => 'REQ-DEL-2',
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'requested_on' => now()->toDateString(),
            'request_type' => 'promotion',
            'purpose' => 'Expired delegate',
            'declaration_at' => now(),
            'status' => RequisitionStatus::PendingManager,
        ]);

        $this->actingAs($delegate)
            ->post(route('requisitions.manager.approve', $requisition))
            ->assertForbidden();
    }

    public function test_manager_can_create_delegation(): void
    {
        $department = Department::query()->create(['name' => 'Sales']);
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'department_id' => $department->id,
        ]);
        $other = User::factory()->create([
            'role' => UserRole::Manager,
            'department_id' => $department->id,
        ]);

        $this->actingAs($manager)
            ->post(route('delegations.store'), [
                'delegate_id' => $other->id,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addWeek()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('approval_delegations', [
            'delegator_id' => $manager->id,
            'delegate_id' => $other->id,
            'active' => true,
        ]);
    }
}
