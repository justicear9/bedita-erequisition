<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionItemLot extends Model
{
    protected $fillable = [
        'requisition_item_id',
        'lot_no',
        'expires_on',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'qty' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RequisitionItem::class, 'requisition_item_id');
    }
}
