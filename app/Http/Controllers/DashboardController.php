<?php

namespace App\Http\Controllers;

use App\Enums\RequisitionStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->role === UserRole::Admin) {
            return redirect()->route('admin.dashboard');
        }

        $myRequests = match ($user->role) {
            UserRole::Requester => Requisition::query()
                ->with(['department', 'requester'])
                ->where('requester_id', $user->id)
                ->latest()
                ->limit(50)
                ->get(),
            UserRole::Manager => Requisition::query()
                ->with(['department', 'requester'])
                ->where('department_id', $user->department_id)
                ->latest()
                ->limit(25)
                ->get(),
            UserRole::InventoryManager, UserRole::Warehouse => Requisition::query()
                ->with(['department', 'requester'])
                ->whereIn('status', [
                    RequisitionStatus::PendingInventory,
                    RequisitionStatus::ReadyForWarehouse,
                    RequisitionStatus::PendingInventoryChangeReview,
                    RequisitionStatus::Released,
                ])
                ->latest()
                ->limit(25)
                ->get(),
            default => collect(),
        };

        $queue = match ($user->role) {
            UserRole::Manager => Requisition::query()
                ->with(['requester', 'department'])
                ->where('status', RequisitionStatus::PendingManager)
                ->whereHas('department', fn ($q) => $q->where('manager_id', $user->id))
                ->latest()
                ->get(),
            UserRole::InventoryManager => Requisition::query()
                ->with(['requester', 'department'])
                ->whereIn('status', [
                    RequisitionStatus::PendingInventory,
                    RequisitionStatus::PendingInventoryChangeReview,
                ])
                ->latest()
                ->get(),
            UserRole::Warehouse => Requisition::query()
                ->with(['requester', 'department'])
                ->where('status', RequisitionStatus::ReadyForWarehouse)
                ->latest()
                ->get(),
            default => collect(),
        };

        return Inertia::render('Dashboard', [
            'myRequests' => $myRequests,
            'queue' => $queue,
            'role' => $user->role->value,
            'roleLabel' => $user->role->label(),
            'hasSignature' => $user->has_signature,
            'stats' => $user->role === UserRole::InventoryManager
                ? $this->inventoryStats()
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryStats(): array
    {
        $monthStart = now()->startOfMonth()->toDateString();

        $statusCounts = Requisition::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $releasedThisMonth = Requisition::query()
            ->where('status', RequisitionStatus::Released)
            ->whereDate('warehouse_signed_at', '>=', $monthStart)
            ->count();

        $unitsRequestedMonth = RequisitionItem::query()
            ->whereHas('requisition', fn ($q) => $q->whereDate('requested_on', '>=', $monthStart))
            ->sum('requested_qty');

        $byDepartment = Requisition::query()
            ->whereDate('requested_on', '>=', $monthStart)
            ->join('departments', 'departments.id', '=', 'requisitions.department_id')
            ->selectRaw('departments.name as label, count(*) as total')
            ->groupBy('departments.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $byType = Requisition::query()
            ->whereDate('requested_on', '>=', $monthStart)
            ->selectRaw('request_type as label, count(*) as total')
            ->groupBy('request_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
            ]);

        return [
            'pending_inventory' => (int) ($statusCounts[RequisitionStatus::PendingInventory->value] ?? 0),
            'change_reviews' => (int) ($statusCounts[RequisitionStatus::PendingInventoryChangeReview->value] ?? 0),
            'ready_for_warehouse' => (int) ($statusCounts[RequisitionStatus::ReadyForWarehouse->value] ?? 0),
            'released_this_month' => $releasedThisMonth,
            'rejected' => (int) ($statusCounts[RequisitionStatus::Rejected->value] ?? 0),
            'total_open' => (int) (
                ($statusCounts[RequisitionStatus::PendingInventory->value] ?? 0)
                + ($statusCounts[RequisitionStatus::PendingInventoryChangeReview->value] ?? 0)
                + ($statusCounts[RequisitionStatus::ReadyForWarehouse->value] ?? 0)
                + ($statusCounts[RequisitionStatus::PendingManager->value] ?? 0)
                + ($statusCounts[RequisitionStatus::ChangesRequested->value] ?? 0)
            ),
            'units_requested_month' => (int) $unitsRequestedMonth,
            'by_department' => $byDepartment,
            'by_type' => $byType,
            'month_label' => now()->format('F Y'),
            'department_count' => Department::query()->count(),
        ];
    }
}
