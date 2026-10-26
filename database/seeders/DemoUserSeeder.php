<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        User::query()->create([
            'name' => 'Ada Admin',
            'email' => 'admin@example.com',
            'password' => $password,
            'role' => UserRole::Admin,
            'department_id' => null,
            'email_verified_at' => now(),
        ]);

        $sales = Department::query()->create(['name' => 'Sales']);
        $marketing = Department::query()->create(['name' => 'Marketing']);
        $medical = Department::query()->create(['name' => 'Medical Affairs']);

        $salesManager = User::query()->create([
            'name' => 'Sam Manager',
            'email' => 'manager.sales@example.com',
            'password' => $password,
            'role' => UserRole::Manager,
            'department_id' => $sales->id,
            'email_verified_at' => now(),
        ]);

        $marketingManager = User::query()->create([
            'name' => 'Morgan Manager',
            'email' => 'manager.marketing@example.com',
            'password' => $password,
            'role' => UserRole::Manager,
            'department_id' => $marketing->id,
            'email_verified_at' => now(),
        ]);

        $medicalManager = User::query()->create([
            'name' => 'Mia Manager',
            'email' => 'manager.medical@example.com',
            'password' => $password,
            'role' => UserRole::Manager,
            'department_id' => $medical->id,
            'email_verified_at' => now(),
        ]);

        $sales->update(['manager_id' => $salesManager->id]);
        $marketing->update(['manager_id' => $marketingManager->id]);
        $medical->update(['manager_id' => $medicalManager->id]);

        User::query()->create([
            'name' => 'Riley Requester',
            'email' => 'requester.sales@example.com',
            'password' => $password,
            'role' => UserRole::Requester,
            'department_id' => $sales->id,
            'email_verified_at' => now(),
        ]);

        User::query()->create([
            'name' => 'Alex Requester',
            'email' => 'requester.marketing@example.com',
            'password' => $password,
            'role' => UserRole::Requester,
            'department_id' => $marketing->id,
            'email_verified_at' => now(),
        ]);

        User::query()->create([
            'name' => 'Jordan Requester',
            'email' => 'requester.medical@example.com',
            'password' => $password,
            'role' => UserRole::Requester,
            'department_id' => $medical->id,
            'email_verified_at' => now(),
        ]);

        User::query()->create([
            'name' => 'Ivy Inventory',
            'email' => 'inventory@example.com',
            'password' => $password,
            'role' => UserRole::InventoryManager,
            'department_id' => null,
            'email_verified_at' => now(),
        ]);

        User::query()->create([
            'name' => 'Wes Warehouse',
            'email' => 'warehouse@example.com',
            'password' => $password,
            'role' => UserRole::Warehouse,
            'department_id' => null,
            'email_verified_at' => now(),
        ]);
    }
}
