<?php

namespace App\Enums;

enum RequisitionStatus: string
{
    case Draft = 'draft';
    case PendingManager = 'pending_manager';
    case ChangesRequested = 'changes_requested';
    case PendingInventory = 'pending_inventory';
    case ReadyForWarehouse = 'ready_for_warehouse';
    case PendingInventoryChangeReview = 'pending_inventory_change_review';
    case Rejected = 'rejected';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingManager => 'Pending Manager',
            self::ChangesRequested => 'Changes Requested',
            self::PendingInventory => 'Pending Inventory',
            self::ReadyForWarehouse => 'Ready for Warehouse',
            self::PendingInventoryChangeReview => 'Pending Change Review',
            self::Rejected => 'Rejected',
            self::Released => 'Released',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingManager, self::PendingInventory, self::PendingInventoryChangeReview => 'amber',
            self::ChangesRequested => 'orange',
            self::ReadyForWarehouse => 'blue',
            self::Rejected => 'red',
            self::Released => 'green',
        };
    }
}
