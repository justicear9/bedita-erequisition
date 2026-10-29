<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use Inertia\Inertia;
use Inertia\Response;

class RequisitionPrintController extends Controller
{
    public function pickList(Requisition $requisition): Response
    {
        $this->authorize('printPickList', $requisition);

        $requisition->load(['requester', 'department.manager', 'items.product', 'items.lots']);

        return Inertia::render('Requisitions/Print', [
            'mode' => 'pick_list',
            'title' => 'Warehouse Pick List',
            'requisition' => $requisition,
        ]);
    }

    public function final(Requisition $requisition): Response
    {
        $this->authorize('printFinal', $requisition);

        $requisition->load(['requester', 'department.manager', 'items.product', 'items.lots']);

        return Inertia::render('Requisitions/Print', [
            'mode' => 'final',
            'title' => 'Final Release Copy',
            'requisition' => $requisition,
        ]);
    }
}
