<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ApprovalDelegation;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DelegationService
{
    public function isActiveDelegateFor(User $actor, User $delegator): bool
    {
        if ($actor->id === $delegator->id) {
            return true;
        }

        return ApprovalDelegation::query()
            ->currentlyActive()
            ->where('delegator_id', $delegator->id)
            ->where('delegate_id', $actor->id)
            ->exists();
    }

    public function canActAsDepartmentManager(User $actor, Requisition $requisition): bool
    {
        $managerId = $requisition->department?->manager_id;
        if (! $managerId) {
            return false;
        }

        if ($actor->id === $managerId) {
            return $actor->role === UserRole::Manager;
        }

        if ($actor->role !== UserRole::Manager) {
            return false;
        }

        return ApprovalDelegation::query()
            ->currentlyActive()
            ->where('delegator_id', $managerId)
            ->where('delegate_id', $actor->id)
            ->exists();
    }

    public function canActAsInventoryManager(User $actor): bool
    {
        if ($actor->role === UserRole::InventoryManager) {
            return true;
        }

        return false;
    }

    /**
     * Inventory decisions: any inventory manager, or an active delegate of an inventory manager
     * (delegate must also hold the inventory_manager role).
     */
    public function canMakeInventoryDecision(User $actor): bool
    {
        if ($actor->role !== UserRole::InventoryManager) {
            return false;
        }

        // All inventory managers can decide; delegates are also inventory managers
        // who were granted authority by another inventory manager (for audit trail).
        return true;
    }

    public function actingForNote(User $actor, ?User $principal): ?string
    {
        if (! $principal || $principal->id === $actor->id) {
            return null;
        }

        return 'Acting as delegate for '.$principal->name;
    }

    public function departmentManagerPrincipal(Requisition $requisition): ?User
    {
        $managerId = $requisition->department?->manager_id;

        return $managerId ? User::query()->find($managerId) : null;
    }

    /**
     * Recipients for manager-queue notifications: department manager + active delegates.
     *
     * @return Collection<int, User>
     */
    public function managerRecipients(Requisition $requisition): Collection
    {
        $manager = $this->departmentManagerPrincipal($requisition);
        if (! $manager) {
            return collect();
        }

        $delegates = ApprovalDelegation::query()
            ->currentlyActive()
            ->where('delegator_id', $manager->id)
            ->with('delegate')
            ->get()
            ->pluck('delegate')
            ->filter();

        return collect([$manager])->merge($delegates)->unique('id')->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function inventoryRecipients(): Collection
    {
        $managers = User::query()
            ->where('role', UserRole::InventoryManager)
            ->get();

        $delegateIds = ApprovalDelegation::query()
            ->currentlyActive()
            ->whereIn('delegator_id', $managers->pluck('id'))
            ->pluck('delegate_id');

        $delegates = User::query()
            ->whereIn('id', $delegateIds)
            ->where('role', UserRole::InventoryManager)
            ->get();

        return $managers->merge($delegates)->unique('id')->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function warehouseRecipients(): Collection
    {
        return User::query()
            ->where('role', UserRole::Warehouse)
            ->get();
    }

    public function create(User $delegator, User $delegate, ?string $startsAt, ?string $endsAt): ApprovalDelegation
    {
        if ($delegator->id === $delegate->id) {
            throw ValidationException::withMessages([
                'delegate_id' => 'You cannot delegate to yourself.',
            ]);
        }

        if (! in_array($delegator->role, [UserRole::Manager, UserRole::InventoryManager], true)) {
            throw ValidationException::withMessages([
                'role' => 'Only managers and Head of Inventory can create delegations.',
            ]);
        }

        if ($delegator->role !== $delegate->role) {
            throw ValidationException::withMessages([
                'delegate_id' => 'Delegate must have the same role as you.',
            ]);
        }

        return ApprovalDelegation::query()->create([
            'delegator_id' => $delegator->id,
            'delegate_id' => $delegate->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'active' => true,
        ]);
    }

    public function revoke(ApprovalDelegation $delegation): void
    {
        $delegation->update(['active' => false]);
    }
}
