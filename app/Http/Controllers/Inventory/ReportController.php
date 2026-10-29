<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\RequestType;
use App\Enums\RequisitionStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Requisition;
use App\Services\InventoryReportInsights;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private InventoryReportInsights $insights) {}

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $with = ['requester', 'department', 'items.product'];
        if (Schema::hasTable('requisition_item_lots')) {
            $with[] = 'items.lots';
        }

        $requisitions = $this->filteredRequisitions($filters)
            ->with($with)
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
            'summary' => $this->insights->build($filters),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $with = ['requester', 'department', 'items.product'];
        $hasLots = Schema::hasTable('requisition_item_lots');
        if ($hasLots) {
            $with[] = 'items.lots';
        }

        $rows = $this->filteredRequisitions($filters)
            ->with($with)
            ->latest('requested_on')
            ->get();

        $filename = 'erequisition-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows, $hasLots) {
            $out = fopen('php://output', 'w');
            $headers = [
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
            ];
            if ($hasLots) {
                $headers[] = 'Lots';
            }
            fputcsv($out, $headers);

            foreach ($rows as $requisition) {
                foreach ($requisition->items as $item) {
                    $qty = $item->fulfilled_qty ?? $item->requested_qty;
                    $row = [
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
                    ];
                    if ($hasLots) {
                        $row[] = $item->lots
                            ->map(fn ($lot) => $lot->lot_no.'|'.$lot->expires_on?->toDateString().'|'.$lot->qty)
                            ->implode('; ');
                    }
                    fputcsv($out, $row);
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
}
