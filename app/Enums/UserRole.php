<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Requester = 'requester';
    case Manager = 'manager';
    case InventoryManager = 'inventory_manager';
    case Warehouse = 'warehouse';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Requester => 'Requester',
            self::Manager => 'Manager',
            self::InventoryManager => 'Head of Inventory',
            self::Warehouse => 'Warehouse',
        };
    }
}
