<?php

namespace App\Console\Commands;

use App\Models\RequisitionEvent;
use Illuminate\Console\Command;

class BackfillWarehouseChangeNotes extends Command
{
    protected $signature = 'requisitions:backfill-change-notes {--force : Run without confirmation}';

    protected $description = 'Replace generic warehouse change activity notes with SKU/qty/reason details from line items';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Update old generic warehouse change notes?', true)) {
            return self::SUCCESS;
        }

        $events = RequisitionEvent::query()
            ->where('action', 'warehouse_submitted_changes')
            ->where(function ($q) {
                $q->whereNull('note')
                    ->orWhere('note', 'Line changes submitted for inventory approval.')
                    ->orWhere('note', 'like', 'Line changes submitted%');
            })
            ->with(['requisition.items.product'])
            ->get();

        $updated = 0;

        foreach ($events as $event) {
            $requisition = $event->requisition;
            if (! $requisition) {
                continue;
            }

            $notes = [];
            foreach ($requisition->items as $item) {
                $fulfilled = $item->fulfilled_qty;
                $reason = trim((string) $item->change_reason);
                $qtyChanged = $fulfilled !== null && (int) $fulfilled !== (int) $item->requested_qty;

                if (! $qtyChanged && $reason === '') {
                    continue;
                }

                $label = $item->product?->sku
                    ? "{$item->product->sku} — {$item->product->description}"
                    : 'Line #'.$item->id;

                $parts = [];
                if ($qtyChanged) {
                    $parts[] = "Qty {$item->requested_qty} → {$fulfilled} {$item->uom}";
                }
                if ($reason !== '') {
                    $parts[] = 'Reason: '.$reason;
                }

                if ($parts !== []) {
                    $notes[] = $label.': '.implode(' · ', $parts);
                }
            }

            if ($notes === []) {
                $this->warn("Event #{$event->id}: no recoverable line-change details");

                continue;
            }

            $event->update([
                'note' => "Changes submitted for inventory approval:\n".implode("\n", $notes),
            ]);
            $updated++;
            $this->info("Updated event #{$event->id} for {$requisition->number}");
        }

        $this->info("Done. Updated {$updated} event(s).");

        return self::SUCCESS;
    }
}
