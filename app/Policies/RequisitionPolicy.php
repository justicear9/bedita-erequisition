<?php

namespace App\Policies;

use App\Enums\RequisitionStatus;
use App\Enums\UserRole;
use App\Models\Requisition;
use App\Models\User;
use App\Services\DelegationService;

class RequisitionPolicy
{
    public function __construct(private DelegationService $delegations) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Requisition $requisition): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Requester => $requisition->requester_id === $user->id,
            UserRole::Manager => $requisition->department_id === $user->department_id
                || $requisition->department?->manager_id === $user->id
                || $this->delegations->canActAsDepartmentManager($user, $requisition),
            UserRole::InventoryManager, UserRole::Warehouse => true,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Requester;
    }

    public function update(User $user, Requisition $requisition): bool
    {
        return $user->role === UserRole::Requester
            && $requisition->requester_id === $user->id
            && in_array($requisition->status, [
                RequisitionStatus::Draft,
                RequisitionStatus::ChangesRequested,
            ], true);
    }

    public function submit(User $user, Requisition $requisition): bool
    {
        return $this->update($user, $requisition);
    }

    public function managerDecide(User $user, Requisition $requisition): bool
    {
        return $requisition->status === RequisitionStatus::PendingManager
            && $this->delegations->canActAsDepartmentManager($user, $requisition);
    }

    public function inventoryDecide(User $user, Requisition $requisition): bool
    {
        return $requisition->status === RequisitionStatus::PendingInventory
            && $this->delegations->canMakeInventoryDecision($user);
    }

    public function inventoryReviewChanges(User $user, Requisition $requisition): bool
    {
        return $requisition->status === RequisitionStatus::PendingInventoryChangeReview
            && $this->delegations->canMakeInventoryDecision($user);
    }

    public function warehouseFulfill(User $user, Requisition $requisition): bool
    {
        return $user->role === UserRole::Warehouse
            && $requisition->status === RequisitionStatus::ReadyForWarehouse;
    }

    public function printPickList(User $user, Requisition $requisition): bool
    {
        return $this->view($user, $requisition) && $requisition->canPrintPickList();
    }

    public function printFinal(User $user, Requisition $requisition): bool
    {
        return $this->view($user, $requisition) && $requisition->canPrintFinal();
    }
}
