<?php

namespace App\Services;

use App\Enums\RequisitionStatus;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class InventoryReportInsights
{
    /**
     * @param  array{from:string,to:string,status:?string,department_id:?int,request_type:?string}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters): array
    {
        $base = $this->filteredRequisitions($filters);
        $itemBase = RequisitionItem::query()
            ->whereIn('requisition_id', (clone $base)->select('requisitions.id'));

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

        $valueRows = (clone $itemBase)
            ->join('requisitions', 'requisitions.id', '=', 'requisition_items.requisition_id')
            ->selectRaw('
                requisitions.status,
                sum(requisition_items.requested_qty * requisition_items.unit_price) as requested_value,
                sum(coalesce(requisition_items.fulfilled_qty, 0) * requisition_items.unit_price) as fulfilled_value,
                sum(requisition_items.requested_qty) as requested_qty,
                sum(coalesce(requisition_items.fulfilled_qty, 0)) as fulfilled_qty,
                sum(
                    case
                        when requisitions.status = ?
                            and requisition_items.fulfilled_qty is not null
                            and requisition_items.fulfilled_qty < requisition_items.requested_qty
                            then (requisition_items.requested_qty - requisition_items.fulfilled_qty) * requisition_items.unit_price
                        else 0
                    end
                ) as shortfall_value
            ', [RequisitionStatus::Released->value])
            ->first();

        $requestedValue = round((float) ($valueRows->requested_value ?? 0), 2);
        $fulfilledValue = round((float) ($valueRows->fulfilled_value ?? 0), 2);
        $shortfallValue = round((float) ($valueRows->shortfall_value ?? 0), 2);
        $requestedQty = (int) ($valueRows->requested_qty ?? 0);
        $fulfilledQty = (int) ($valueRows->fulfilled_qty ?? 0);
        $fillRate = $requestedQty > 0 ? round(($fulfilledQty / $requestedQty) * 100, 1) : 0.0;

        $pipelineStatuses = [
            RequisitionStatus::PendingManager->value,
            RequisitionStatus::PendingInventory->value,
            RequisitionStatus::ReadyForWarehouse->value,
            RequisitionStatus::PendingInventoryChangeReview->value,
            RequisitionStatus::ChangesRequested->value,
        ];

        $pipelineValue = round((float) (clone $itemBase)
            ->join('requisitions', 'requisitions.id', '=', 'requisition_items.requisition_id')
            ->whereIn('requisitions.status', $pipelineStatuses)
            ->selectRaw('sum(requisition_items.requested_qty * requisition_items.unit_price) as value')
            ->value('value'), 2);

        $releasedValue = round((float) (clone $itemBase)
            ->join('requisitions', 'requisitions.id', '=', 'requisition_items.requisition_id')
            ->where('requisitions.status', RequisitionStatus::Released->value)
            ->selectRaw('sum(coalesce(requisition_items.fulfilled_qty, requisition_items.requested_qty) * requisition_items.unit_price) as value')
            ->value('value'), 2);

        $total = (clone $base)->count();
        $rejected = (int) ($byStatus[RequisitionStatus::Rejected->value] ?? 0);
        $rejectionRate = $total > 0 ? round(($rejected / $total) * 100, 1) : 0.0;

        $topProducts = (clone $itemBase)
            ->join('products', 'products.id', '=', 'requisition_items.product_id')
            ->selectRaw('
                products.sku,
                products.description,
                sum(requisition_items.requested_qty) as qty,
                sum(requisition_items.requested_qty * requisition_items.unit_price) as value
            ')
            ->groupBy('products.id', 'products.sku', 'products.description')
            ->orderByDesc('value')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'sku' => $row->sku,
                'description' => $row->description,
                'qty' => (int) $row->qty,
                'value' => round((float) $row->value, 2),
            ]);

        $series = $this->dailySeries($filters, $base);
        $forecast = $this->forecast($filters, $base);
        $shortfalls = $this->shortfalls($base);
        $insights = $this->insightCards(
            $filters,
            $base,
            $pipelineValue,
            $shortfallValue,
            $topProducts,
            $shortfalls,
        );

        return [
            'total' => $total,
            'released' => (int) ($byStatus[RequisitionStatus::Released->value] ?? 0),
            'pending_inventory' => (int) ($byStatus[RequisitionStatus::PendingInventory->value] ?? 0),
            'rejected' => $rejected,
            'by_status' => collect(RequisitionStatus::cases())->map(fn ($s) => [
                'status' => $s->value,
                'label' => $s->label(),
                'total' => (int) ($byStatus[$s->value] ?? 0),
            ])->values(),
            'by_department' => $byDepartment,
            'top_products' => $topProducts,
            'kpis' => [
                'requested_value' => $requestedValue,
                'released_value' => $releasedValue,
                'pipeline_value' => $pipelineValue,
                'shortfall_value' => $shortfallValue,
                'fill_rate' => $fillRate,
                'rejection_rate' => $rejectionRate,
            ],
            'series' => $series,
            'forecast' => $forecast,
            'shortfalls' => $shortfalls,
            'insights' => $insights,
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
     * @return list<array{date:string,count:int,value:float}>
     */
    private function dailySeries(array $filters, $base): array
    {
        $rows = (clone $base)
            ->leftJoin('requisition_items', 'requisition_items.requisition_id', '=', 'requisitions.id')
            ->selectRaw('
                date(requisitions.requested_on) as day,
                count(distinct requisitions.id) as total,
                coalesce(sum(requisition_items.requested_qty * requisition_items.unit_price), 0) as value
            ')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->startOfDay();
        $days = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $row = $rows->get($key);
            $days[] = [
                'date' => $key,
                'count' => (int) ($row->total ?? 0),
                'value' => round((float) ($row->value ?? 0), 2),
            ];
        }

        return $days;
    }

    /**
     * @param  array{from:string,to:string,status:?string,department_id:?int,request_type:?string}  $filters
     * @return list<array{sku:string,description:string,avg_daily:float,projected_30:float,value_30:float}>
     */
    private function forecast(array $filters, $base): array
    {
        $from = Carbon::parse($filters['from']);
        $to = Carbon::parse($filters['to']);
        $spanDays = max(1, $from->diffInDays($to) + 1);

        return RequisitionItem::query()
            ->whereIn('requisition_id', (clone $base)->select('requisitions.id'))
            ->join('products', 'products.id', '=', 'requisition_items.product_id')
            ->selectRaw('
                products.sku,
                products.description,
                sum(requisition_items.requested_qty) as qty,
                avg(requisition_items.unit_price) as avg_price
            ')
            ->groupBy('products.id', 'products.sku', 'products.description')
            ->orderByDesc('qty')
            ->limit(8)
            ->get()
            ->map(function ($row) use ($spanDays) {
                $avgDaily = round(((int) $row->qty) / $spanDays, 2);
                $projected30 = round($avgDaily * 30, 1);

                return [
                    'sku' => $row->sku,
                    'description' => $row->description,
                    'avg_daily' => $avgDaily,
                    'projected_30' => $projected30,
                    'value_30' => round($projected30 * (float) $row->avg_price, 2),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{sku:string,description:string,requested:int,fulfilled:int,shortfall:int,value:float}>
     */
    private function shortfalls($base): array
    {
        return RequisitionItem::query()
            ->whereIn('requisition_id', (clone $base)
                ->where('status', RequisitionStatus::Released->value)
                ->select('requisitions.id'))
            ->join('products', 'products.id', '=', 'requisition_items.product_id')
            ->whereNotNull('requisition_items.fulfilled_qty')
            ->whereColumn('requisition_items.fulfilled_qty', '<', 'requisition_items.requested_qty')
            ->selectRaw('
                products.sku,
                products.description,
                sum(requisition_items.requested_qty) as requested,
                sum(requisition_items.fulfilled_qty) as fulfilled,
                sum(requisition_items.requested_qty - requisition_items.fulfilled_qty) as shortfall,
                sum((requisition_items.requested_qty - requisition_items.fulfilled_qty) * requisition_items.unit_price) as value
            ')
            ->groupBy('products.id', 'products.sku', 'products.description')
            ->orderByDesc('value')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'sku' => $row->sku,
                'description' => $row->description,
                'requested' => (int) $row->requested,
                'fulfilled' => (int) $row->fulfilled,
                'shortfall' => (int) $row->shortfall,
                'value' => round((float) $row->value, 2),
            ])
            ->all();
    }

    /**
     * @param  array{from:string,to:string,status:?string,department_id:?int,request_type:?string}  $filters
     * @param  Collection<int, array{sku:string,description:string,qty:int,value:float}>  $topProducts
     * @param  list<array{sku:string,description:string,requested:int,fulfilled:int,shortfall:int,value:float}>  $shortfalls
     * @return list<array{title:string,detail:string,tone:string}>
     */
    private function insightCards(
        array $filters,
        $base,
        float $pipelineValue,
        float $shortfallValue,
        Collection $topProducts,
        array $shortfalls,
    ): array {
        $cards = [];

        $stuck = (clone $base)
            ->whereIn('status', [
                RequisitionStatus::PendingInventory->value,
                RequisitionStatus::PendingInventoryChangeReview->value,
                RequisitionStatus::ReadyForWarehouse->value,
            ])
            ->where('updated_at', '<', now()->subDays(3))
            ->count();

        if ($stuck > 0) {
            $cards[] = [
                'title' => 'Stuck high-value pipeline',
                'detail' => "{$stuck} requisition(s) idle 3+ days in inventory/warehouse. Pipeline value ≈ GHS ".number_format($pipelineValue, 2).'.',
                'tone' => 'amber',
            ];
        }

        if ($shortfalls !== []) {
            $top = $shortfalls[0];
            $cards[] = [
                'title' => 'Repeated shortfalls',
                'detail' => "{$top['description']} short by {$top['shortfall']} units (GHS ".number_format($top['value'], 2).') in released orders.',
                'tone' => 'red',
            ];
        }

        $series = $this->dailySeries($filters, $base);
        if (count($series) >= 7) {
            $recent = array_slice($series, -3);
            $prior = array_slice($series, -7, 4);
            $recentAvg = collect($recent)->avg('value') ?: 0;
            $priorAvg = collect($prior)->avg('value') ?: 0;
            if ($priorAvg > 0 && $recentAvg > $priorAvg * 1.5) {
                $cards[] = [
                    'title' => 'Demand spike',
                    'detail' => 'Last 3 days average request value is '.round((($recentAvg / $priorAvg) - 1) * 100).'% above the prior 4 days.',
                    'tone' => 'blue',
                ];
            }
        }

        $zeroPrice = RequisitionItem::query()
            ->whereIn('requisition_id', (clone $base)->select('requisitions.id'))
            ->where(function ($q) {
                $q->whereNull('unit_price')->orWhere('unit_price', 0);
            })
            ->count();

        if ($zeroPrice > 0) {
            $cards[] = [
                'title' => 'Zero-price movers',
                'detail' => "{$zeroPrice} line(s) have no unit price — value KPIs understate true exposure.",
                'tone' => 'slate',
            ];
        }

        $topValue = (float) ($topProducts->first()['value'] ?? 0);
        $allValue = (float) $topProducts->sum('value');
        if ($allValue > 0 && $topValue / $allValue >= 0.4) {
            $top = $topProducts->first();
            $cards[] = [
                'title' => 'Concentration risk',
                'detail' => ($top['description'] ?? 'Top SKU').' is '.round(($topValue / $allValue) * 100).'% of top-product request value.',
                'tone' => 'amber',
            ];
        }

        if ($shortfallValue > 0 && $cards === []) {
            $cards[] = [
                'title' => 'Shortfall exposure',
                'detail' => 'Released shortfalls total GHS '.number_format($shortfallValue, 2).' in this range.',
                'tone' => 'red',
            ];
        }

        if ($cards === []) {
            $cards[] = [
                'title' => 'Stable window',
                'detail' => 'No sharp shortfalls, spikes, or stuck pipeline signals in this filter range.',
                'tone' => 'green',
            ];
        }

        return array_slice($cards, 0, 5);
    }
}
