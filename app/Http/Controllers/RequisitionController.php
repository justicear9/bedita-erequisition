<?php

namespace App\Http\Controllers;

use App\Enums\RequestType;
use App\Enums\RequisitionStatus;
use App\Models\Product;
use App\Models\Requisition;
use App\Services\RequisitionWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RequisitionController extends Controller
{
    public function __construct(private RequisitionWorkflow $workflow) {}

    public function create(Request $request): Response
    {
        $this->authorize('create', Requisition::class);

        $user = $request->user()->load('department');

        return Inertia::render('Requisitions/Form', [
            'requisition' => null,
            'declarationText' => Requisition::DECLARATION_TEXT,
            'requestTypes' => collect(RequestType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'requester' => [
                'name' => $user->name,
                'department' => $user->department?->name,
            ],
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Requisition::class);

        $data = $this->validatedForm($request);
        $user = $request->user();

        $requisition = DB::transaction(function () use ($data, $user) {
            $requisition = Requisition::create([
                'number' => $this->nextNumber(),
                'requester_id' => $user->id,
                'department_id' => $user->department_id,
                'requested_on' => $data['requested_on'],
                'request_type' => $data['request_type'],
                'request_type_other' => $data['request_type_other'] ?? null,
                'purpose' => $data['purpose'],
                'declaration_at' => ! empty($data['declaration']) ? now() : null,
                'status' => RequisitionStatus::Draft,
            ]);

            $this->syncItems($requisition, $data['items']);

            $this->workflow->record(
                $requisition,
                $user,
                'created',
                null,
                RequisitionStatus::Draft,
            );

            return $requisition;
        });

        if ($request->boolean('should_submit')) {
            if (! $requisition->declaration_at) {
                $requisition->update(['declaration_at' => now()]);
            }

            try {
                $this->workflow->submit($requisition->fresh(['department']), $user);

                return redirect()
                    ->route('requisitions.show', $requisition)
                    ->with('success', 'Requisition submitted.');
            } catch (ValidationException $e) {
                return redirect()
                    ->route('requisitions.show', $requisition)
                    ->withErrors($e->errors())
                    ->with('error', 'Draft saved, but it could not be submitted. '.$this->firstError($e));
            }
        }

        return redirect()
            ->route('requisitions.show', $requisition)
            ->with('success', 'Draft saved.');
    }

    public function show(Requisition $requisition): Response
    {
        $this->authorize('view', $requisition);

        $requisition->load([
            'requester',
            'department.manager',
            'items.product',
            'items.lots',
            'events.user',
        ]);

        $user = request()->user();

        return Inertia::render('Requisitions/Show', [
            'requisition' => $requisition,
            'declarationText' => Requisition::DECLARATION_TEXT,
            'permissions' => [
                'update' => $user->can('update', $requisition),
                'submit' => $user->can('submit', $requisition),
                'managerDecide' => $user->can('managerDecide', $requisition),
                'inventoryDecide' => $user->can('inventoryDecide', $requisition),
                'inventoryReviewChanges' => $user->can('inventoryReviewChanges', $requisition),
                'warehouseFulfill' => $user->can('warehouseFulfill', $requisition),
                'printPickList' => $user->can('printPickList', $requisition),
                'printFinal' => $user->can('printFinal', $requisition),
            ],
        ]);
    }

    public function edit(Requisition $requisition): Response
    {
        $this->authorize('update', $requisition);

        $requisition->load(['items.product', 'requester', 'department']);
        $user = request()->user()->load('department');

        return Inertia::render('Requisitions/Form', [
            'requisition' => [
                ...$requisition->toArray(),
                'requested_on' => $requisition->requested_on->toDateString(),
                'items' => $requisition->items,
            ],
            'declarationText' => Requisition::DECLARATION_TEXT,
            'requestTypes' => collect(RequestType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'requester' => [
                'name' => $user->name,
                'department' => $user->department?->name,
            ],
            'today' => now()->toDateString(),
        ]);
    }

    public function update(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('update', $requisition);

        $data = $this->validatedForm($request);

        DB::transaction(function () use ($data, $requisition, $request) {
            $requisition->update([
                'requested_on' => $data['requested_on'],
                'request_type' => $data['request_type'],
                'request_type_other' => $data['request_type_other'] ?? null,
                'purpose' => $data['purpose'],
                'declaration_at' => ! empty($data['declaration'])
                    ? ($requisition->declaration_at ?? now())
                    : $requisition->declaration_at,
            ]);

            $requisition->items()->delete();
            $this->syncItems($requisition, $data['items']);

            $this->workflow->record(
                $requisition,
                $request->user(),
                'updated',
                $requisition->status,
                $requisition->status,
            );
        });

        if ($request->boolean('should_submit')) {
            $requisition->refresh();
            if (! $requisition->declaration_at) {
                $requisition->update(['declaration_at' => now()]);
            }

            try {
                $this->workflow->submit($requisition->fresh(['department']), $request->user());

                return redirect()
                    ->route('requisitions.show', $requisition)
                    ->with('success', 'Requisition submitted.');
            } catch (ValidationException $e) {
                return redirect()
                    ->route('requisitions.show', $requisition)
                    ->withErrors($e->errors())
                    ->with('error', 'Changes saved, but it could not be submitted. '.$this->firstError($e));
            }
        }

        return redirect()
            ->route('requisitions.show', $requisition)
            ->with('success', 'Draft saved.');
    }

    private function firstError(ValidationException $e): string
    {
        $messages = $e->errors();
        $first = reset($messages);

        return is_array($first) ? (string) ($first[0] ?? '') : '';
    }

    public function submit(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('submit', $requisition);
        $this->workflow->submit($requisition, $request->user());

        return back()->with('success', 'Requisition submitted.');
    }

    public function managerApprove(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('managerDecide', $requisition);
        $this->workflow->managerApprove($requisition, $request->user());

        return back()->with('success', 'Approved — sent to Inventory Manager.');
    }

    public function managerSendBack(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('managerDecide', $requisition);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->workflow->managerSendBack($requisition, $request->user(), $data['note']);

        return back()->with('success', 'Sent back to requester.');
    }

    public function managerReject(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('managerDecide', $requisition);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->workflow->managerReject($requisition, $request->user(), $data['reason']);

        return back()->with('success', 'Requisition rejected.');
    }

    public function inventoryApprove(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('inventoryDecide', $requisition);
        $this->workflow->inventoryApprove($requisition, $request->user());

        return back()->with('success', 'Approved — ready for warehouse.');
    }

    public function inventorySendBack(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('inventoryDecide', $requisition);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->workflow->inventorySendBack($requisition, $request->user(), $data['note']);

        return back()->with('success', 'Sent back to requester.');
    }

    public function inventoryReject(Request $request, Requisition $requisition): RedirectResponse
    {
        $canDecide = $request->user()->can('inventoryDecide', $requisition);
        $canReview = $request->user()->can('inventoryReviewChanges', $requisition);
        abort_unless($canDecide || $canReview, 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->workflow->inventoryReject($requisition, $request->user(), $data['reason']);

        return back()->with('success', 'Requisition rejected.');
    }

    public function inventoryApproveChanges(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('inventoryReviewChanges', $requisition);
        $this->workflow->inventoryApproveChanges($requisition, $request->user());

        return back()->with('success', 'Line changes approved — warehouse can release.');
    }

    public function inventorySendChangesBack(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('inventoryReviewChanges', $requisition);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->workflow->inventorySendChangesBackToWarehouse($requisition, $request->user(), $data['note']);

        return back()->with('success', 'Sent back to warehouse.');
    }

    public function warehouseRelease(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('warehouseFulfill', $requisition);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer', 'exists:requisition_items,id'],
            'lines.*.fulfilled_qty' => ['required', 'integer', 'min:0'],
            'lines.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.change_reason' => ['nullable', 'string', 'max:2000'],
            'lines.*.lots' => ['nullable', 'array'],
            'lines.*.lots.*.lot_no' => ['required_with:lines.*.lots', 'string', 'max:100'],
            'lines.*.lots.*.expires_on' => ['required_with:lines.*.lots', 'date'],
            'lines.*.lots.*.qty' => ['required_with:lines.*.lots', 'integer', 'min:1'],
        ]);

        try {
            $this->workflow->warehouseRelease($requisition, $request->user(), $data['lines']);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->with('error', $this->firstError($e) ?: 'Could not release or submit changes.');
        }

        $requisition->refresh();

        $message = $requisition->status === RequisitionStatus::Released
            ? 'Released.'
            : 'Changes sent to Head of Inventory for approval.';

        return back()->with('success', $message);
    }

    private function validatedForm(Request $request): array
    {
        $submitting = $request->boolean('should_submit');

        return $request->validate([
            'requested_on' => ['required', 'date', 'after_or_equal:today'],
            'request_type' => ['required', Rule::enum(RequestType::class)],
            'request_type_other' => ['nullable', 'required_if:request_type,other', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:5000'],
            'declaration' => [$submitting ? 'accepted' : 'nullable', 'boolean'],
            'should_submit' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);
    }

    /**
     * @param  array<int, array{product_id:int, qty:int}>  $items
     */
    private function syncItems(Requisition $requisition, array $items): void
    {
        $merged = [];
        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $merged[$productId] = ($merged[$productId] ?? 0) + (int) $item['qty'];
        }

        foreach ($merged as $productId => $qty) {
            $product = Product::query()->findOrFail($productId);
            $requisition->items()->create([
                'product_id' => $product->id,
                'requested_qty' => $qty,
                'uom' => $product->uom,
                'unit_price' => $product->price,
            ]);
        }
    }

    private function nextNumber(): string
    {
        $seq = Requisition::query()->lockForUpdate()->count() + 1;

        return 'REQ-'.now()->format('Ymd').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
