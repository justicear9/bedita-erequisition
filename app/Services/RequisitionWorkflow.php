<?php

namespace App\Services;

use App\Enums\RequisitionStatus;
use App\Enums\UserRole;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequisitionWorkflow
{
    public function __construct(private SignatureService $signatures) {}

    public function record(
        Requisition $requisition,
        User $actor,
        string $action,
        ?RequisitionStatus $from,
        ?RequisitionStatus $to,
        ?string $note = null,
    ): void {
        $requisition->events()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'note' => $note,
        ]);
    }

    public function submit(Requisition $requisition, User $actor): void
    {
        $this->assertRole($actor, [UserRole::Requester]);
        $this->assertOwner($requisition, $actor);
        $this->assertStatus($requisition, [RequisitionStatus::Draft, RequisitionStatus::ChangesRequested]);
        $this->assertHasSignature($actor);

        if (! $requisition->declaration_at) {
            throw ValidationException::withMessages([
                'declaration' => 'You must accept the declaration before submitting.',
            ]);
        }

        if ($requisition->items()->count() < 1) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product line before submitting.',
            ]);
        }

        if (! $requisition->department?->manager_id) {
            throw ValidationException::withMessages([
                'department' => 'Your department has no manager assigned.',
            ]);
        }

        $from = $requisition->status;
        $path = $this->signatures->snapshotForRequisition($actor, 'requester-'.$requisition->id);

        $requisition->update([
            'status' => RequisitionStatus::PendingManager,
            'requester_signature_path' => $path,
            'requester_signed_at' => now(),
        ]);
        $this->record($requisition, $actor, 'submitted', $from, RequisitionStatus::PendingManager);
    }

    public function managerApprove(Requisition $requisition, User $actor): void
    {
        $this->assertRole($actor, [UserRole::Manager]);
        $this->assertDepartmentManager($requisition, $actor);
        $this->assertStatus($requisition, [RequisitionStatus::PendingManager]);
        $this->assertHasSignature($actor);

        $from = $requisition->status;
        $path = $this->signatures->snapshotForRequisition($actor, 'manager-'.$requisition->id);

        $requisition->update([
            'status' => RequisitionStatus::PendingInventory,
            'manager_signature_path' => $path,
            'manager_signed_at' => now(),
        ]);
        $this->record($requisition, $actor, 'manager_approved', $from, RequisitionStatus::PendingInventory);
    }

    public function managerSendBack(Requisition $requisition, User $actor, string $note): void
    {
        $this->assertRole($actor, [UserRole::Manager]);
        $this->assertDepartmentManager($requisition, $actor);
        $this->assertStatus($requisition, [RequisitionStatus::PendingManager]);
        $this->assertNote($note);

        $from = $requisition->status;
        $requisition->update(['status' => RequisitionStatus::ChangesRequested]);
        $this->record($requisition, $actor, 'manager_sent_back', $from, RequisitionStatus::ChangesRequested, $note);
    }

    public function managerReject(Requisition $requisition, User $actor, string $reason): void
    {
        $this->assertRole($actor, [UserRole::Manager]);
        $this->assertDepartmentManager($requisition, $actor);
        $this->assertStatus($requisition, [RequisitionStatus::PendingManager]);
        $this->assertNote($reason, 'reason');

        $from = $requisition->status;
        $requisition->update(['status' => RequisitionStatus::Rejected]);
        $this->record($requisition, $actor, 'manager_rejected', $from, RequisitionStatus::Rejected, $reason);
    }

    public function inventoryApprove(Requisition $requisition, User $actor): void
    {
        $this->assertRole($actor, [UserRole::InventoryManager]);
        $this->assertStatus($requisition, [RequisitionStatus::PendingInventory]);
        $this->assertHasSignature($actor);

        $from = $requisition->status;
        $path = $this->signatures->snapshotForRequisition($actor, 'inventory-'.$requisition->id);

        $requisition->update([
            'status' => RequisitionStatus::ReadyForWarehouse,
            'inventory_signature_path' => $path,
            'inventory_signed_at' => now(),
        ]);
        $this->record($requisition, $actor, 'inventory_approved', $from, RequisitionStatus::ReadyForWarehouse);
    }

    public function inventorySendBack(Requisition $requisition, User $actor, string $note): void
    {
        $this->assertRole($actor, [UserRole::InventoryManager]);
        $this->assertStatus($requisition, [RequisitionStatus::PendingInventory]);
        $this->assertNote($note);

        $from = $requisition->status;
        $requisition->update(['status' => RequisitionStatus::ChangesRequested]);
        $this->record($requisition, $actor, 'inventory_sent_back', $from, RequisitionStatus::ChangesRequested, $note);
    }

    public function inventoryReject(Requisition $requisition, User $actor, string $reason): void
    {
        $this->assertRole($actor, [UserRole::InventoryManager]);
        $this->assertStatus($requisition, [
            RequisitionStatus::PendingInventory,
            RequisitionStatus::PendingInventoryChangeReview,
        ]);
        $this->assertNote($reason, 'reason');

        $from = $requisition->status;
        $requisition->update(['status' => RequisitionStatus::Rejected]);
        $this->record($requisition, $actor, 'inventory_rejected', $from, RequisitionStatus::Rejected, $reason);
    }

    /**
     * @param  array<int, array{id:int, fulfilled_qty:int, product_id?:int, change_reason?:string|null}>  $lines
     */
    public function warehouseRelease(Requisition $requisition, User $actor, array $lines): void
    {
        $this->assertRole($actor, [UserRole::Warehouse]);
        $this->assertStatus($requisition, [RequisitionStatus::ReadyForWarehouse]);

        DB::transaction(function () use ($requisition, $actor, $lines) {
            $hasChanges = $this->applyWarehouseLines($requisition, $lines);
            $from = $requisition->status;

            if ($hasChanges) {
                $requisition->update(['status' => RequisitionStatus::PendingInventoryChangeReview]);
                $this->record(
                    $requisition,
                    $actor,
                    'warehouse_submitted_changes',
                    $from,
                    RequisitionStatus::PendingInventoryChangeReview,
                    'Line changes submitted for inventory approval.',
                );

                return;
            }

            $this->assertHasSignature($actor);
            $path = $this->signatures->snapshotForRequisition($actor, 'warehouse-'.$requisition->id);

            $requisition->update([
                'status' => RequisitionStatus::Released,
                'warehouse_signature_path' => $path,
                'warehouse_signed_at' => now(),
            ]);
            $this->record($requisition, $actor, 'warehouse_released', $from, RequisitionStatus::Released);
        });
    }

    public function inventoryApproveChanges(Requisition $requisition, User $actor): void
    {
        $this->assertRole($actor, [UserRole::InventoryManager]);
        $this->assertStatus($requisition, [RequisitionStatus::PendingInventoryChangeReview]);
        $this->assertHasSignature($actor);

        $from = $requisition->status;
        $path = $this->signatures->snapshotForRequisition($actor, 'inventory-change-'.$requisition->id);

        $requisition->update([
            'status' => RequisitionStatus::ReadyForWarehouse,
            'inventory_signature_path' => $path,
            'inventory_signed_at' => now(),
        ]);
        $this->record($requisition, $actor, 'inventory_approved_changes', $from, RequisitionStatus::ReadyForWarehouse);

        foreach ($requisition->items as $item) {
            $item->update([
                'requested_qty' => $item->fulfilled_qty ?? $item->requested_qty,
                'change_reason' => null,
            ]);
        }
    }

    public function inventorySendChangesBackToWarehouse(Requisition $requisition, User $actor, string $note): void
    {
        $this->assertRole($actor, [UserRole::InventoryManager]);
        $this->assertStatus($requisition, [RequisitionStatus::PendingInventoryChangeReview]);
        $this->assertNote($note);

        $from = $requisition->status;
        $requisition->update(['status' => RequisitionStatus::ReadyForWarehouse]);
        $this->record(
            $requisition,
            $actor,
            'inventory_sent_changes_back',
            $from,
            RequisitionStatus::ReadyForWarehouse,
            $note,
        );
    }

    /**
     * @param  array<int, array{id:int, fulfilled_qty:int, product_id?:int, change_reason?:string|null}>  $lines
     */
    private function applyWarehouseLines(Requisition $requisition, array $lines): bool
    {
        $hasChanges = false;
        $items = $requisition->items()->get()->keyBy('id');

        foreach ($lines as $line) {
            $item = $items->get($line['id'] ?? null);
            if (! $item) {
                throw ValidationException::withMessages([
                    'lines' => 'Invalid line item on this requisition.',
                ]);
            }

            $fulfilledQty = (int) $line['fulfilled_qty'];
            $productId = isset($line['product_id']) ? (int) $line['product_id'] : $item->product_id;
            $changed = $fulfilledQty !== (int) $item->requested_qty || $productId !== (int) $item->product_id;

            if ($changed) {
                $reason = trim((string) ($line['change_reason'] ?? ''));
                if ($reason === '') {
                    throw ValidationException::withMessages([
                        'lines' => 'A reason is required for each changed line item.',
                    ]);
                }
                $hasChanges = true;
            } else {
                $reason = null;
            }

            $updates = [
                'fulfilled_qty' => $fulfilledQty,
                'change_reason' => $reason,
            ];

            if ($productId !== (int) $item->product_id) {
                $product = \App\Models\Product::query()->findOrFail($productId);
                $updates['product_id'] = $product->id;
                $updates['uom'] = $product->uom;
                $updates['unit_price'] = $product->price;
            }

            $item->update($updates);
        }

        return $hasChanges;
    }

    private function assertHasSignature(User $actor): void
    {
        if (! $actor->signature_path) {
            throw ValidationException::withMessages([
                'signature' => 'Add your signature in Profile before continuing.',
            ]);
        }
    }

    private function assertRole(User $actor, array $roles): void
    {
        if (! in_array($actor->role, $roles, true)) {
            abort(403, 'You are not allowed to perform this action.');
        }
    }

    private function assertOwner(Requisition $requisition, User $actor): void
    {
        if ($requisition->requester_id !== $actor->id) {
            abort(403, 'You can only submit your own requisitions.');
        }
    }

    private function assertDepartmentManager(Requisition $requisition, User $actor): void
    {
        if ($requisition->department?->manager_id !== $actor->id) {
            abort(403, 'You are not the manager for this department.');
        }
    }

    /**
     * @param  list<RequisitionStatus>  $statuses
     */
    private function assertStatus(Requisition $requisition, array $statuses): void
    {
        if (! in_array($requisition->status, $statuses, true)) {
            throw ValidationException::withMessages([
                'status' => 'This requisition is not in a state that allows that action.',
            ]);
        }
    }

    private function assertNote(string $note, string $field = 'note'): void
    {
        if (trim($note) === '') {
            throw ValidationException::withMessages([
                $field => 'A note is required.',
            ]);
        }
    }
}
