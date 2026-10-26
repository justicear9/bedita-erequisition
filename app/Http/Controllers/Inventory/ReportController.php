<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\RequestType;
use App\Enums\RequisitionStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $requisitions = $this->filteredRequisitions($filters)
            ->with(['requester', 'department', 'items.product'])
            ->latest('requested_on')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Inventory/Reports', [
            'filters' => [
                'from' => $filters['from'],
                'to' => $filters['to'],
                'status' => $filters['status'],
                'department_id' => $filters['department_id'],
                'request_type' => $filters['request_type'],
            ],
            'statuses' => collect(RequisitionStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'requestTypes' => collect(RequestType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'requisitions' => $requisitions,
            'summary' => $this->summary($filters),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->filteredRequisitions($filters)
            ->with(['requester', 'department', 'items.product'])
            ->latest('requested_on')
            ->get();

        $filename = 'erequisition-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Number',
                'Date',
                'Status',
                'Requester',
                'Department',
                'Request Type',
                'Purpose',
                'SKU',
                'Product',
                'UoM',
                'Requested Qty',
                'Fulfilled Qty',
                'Unit Price',
                'Line Total',
            ]);

            foreach ($rows as $requisition) {
                foreach ($requisition->items as $item) {
                    $qty = $item->fulfilled_qty ?? $item->requested_qty;
                    fputcsv($out, [
                        $requisition->number,
                        optional($requisition->requested_on)->toDateString(),
                        $requisition->status->value,
                        $requisition->requester?->name,
                        $requisition->department?->name,
                        $requisition->request_type->value,
                        $requisition->purpose,
                        $item->product?->sku,
                        $item->product?->description,
                        $item->uom,
                        $item->requested_qty,
                        $item->fulfilled_qty,
                        $item->unit_price,
                        round((float) $item->unit_price * (int) $qty, 2),
                    ]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array{from:string,to:string,status:?string,department_id:?int,request_type:?string}
     */
    private function filters(Request $request): array
    {
        return [
            'from' => $request->string('from')->toString() ?: now()->startOfMonth()->toDateString(),
            'to' => $request->string('to')->toString() ?: now()->toDateString(),
            'status' => $request->filled('status') ? $request->string('status')->toString() : null,
            'department_id' => $request->filled('department_id') ? (int) $request->input('department_id') : null,
            'request_type' => $request->filled('request_type') ? $request->string('request_type')->toString() : null,
        ];
    }

    /**
     * @param  array{from:string,to:string,status:?string,department_id:?int,request_type:?string}  $filters
     */
    private function filteredRequisitions(array $filters)
    {
        return Requisition::query()
            ->when($filters['from'], fn ($q) => $q->whereDate('requested_on', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('requested_on', '<=', $filters['to']))
            ->when($filters['status'], fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['department_id'], fn ($q) => $q->where('department_id', $filters['department_id']))
            ->when($filters['request_type'], fn ($q) => $q->where('request_type', $filters['request_type']));
    }

    /**
     * @param  array{from:string,to:string,status:?string,department_id:?int,request_type:?string}  $filters
     * @return array<string, mixed>
     */
    private function summary(array $filters): array
    {
        $base = $this->filteredRequisitions($filters);

        $byStatus = (clone $base)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byDepartment = (clone $base)
            ->join('departments', 'departments.id', '=', 'requisitions.department_id')
            ->selectRaw('departments.name as department, count(*) as total')
            ->groupBy('departments.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $topProducts = RequisitionItem::query()
            ->whereIn('requisition_id', (clone $base)->select('requisitions.id'))
            ->join('products', 'products.id', '=', 'requisition_items.product_id')
            ->selectRaw('products.sku, products.description, sum(requisition_items.requested_qty) as qty')
            ->groupBy('products.id', 'products.sku', 'products.description')
            ->orderByDesc('qty')
            ->limit(8)
            ->get();

        return [
            'total' => (clone $base)->count(),
            'released' => (int) ($byStatus[RequisitionStatus::Released->value] ?? 0),
            'pending_inventory' => (int) ($byStatus[RequisitionStatus::PendingInventory->value] ?? 0),
            'rejected' => (int) ($byStatus[RequisitionStatus::Rejected->value] ?? 0),
            'by_status' => collect(RequisitionStatus::cases())->map(fn ($s) => [
                'status' => $s->value,
                'label' => $s->label(),
                'total' => (int) ($byStatus[$s->value] ?? 0),
            ])->values(),
            'by_department' => $byDepartment,
            'top_products' => $topProducts,
        ];
    }
}
