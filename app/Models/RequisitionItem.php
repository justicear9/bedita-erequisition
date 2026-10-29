<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequisitionItem extends Model
{
    protected $fillable = [
        'requisition_id',
        'product_id',
        'requested_qty',
        'fulfilled_qty',
        'uom',
        'unit_price',
        'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_qty' => 'integer',
            'fulfilled_qty' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(RequisitionItemLot::class);
    }

    public function hasWarehouseChange(): bool
    {
        if ($this->fulfilled_qty === null) {
            return false;
        }

        return (int) $this->fulfilled_qty !== (int) $this->requested_qty;
    }
}
