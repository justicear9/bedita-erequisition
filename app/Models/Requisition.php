<?php

namespace App\Models;

use App\Enums\RequestType;
use App\Enums\RequisitionStatus;
use App\Services\SignatureService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requisition extends Model
{
    public const DECLARATION_TEXT = 'I confirm that the information provided is correct. I agree to use the samples only for the approved purpose and to account for or return any unused quantity where required.';

    protected $fillable = [
        'number',
        'requester_id',
        'department_id',
        'requested_on',
        'request_type',
        'request_type_other',
        'purpose',
        'declaration_at',
        'status',
        'requester_signature_path',
        'requester_signed_at',
        'manager_signature_path',
        'manager_signed_at',
        'inventory_signature_path',
        'inventory_signed_at',
        'warehouse_signature_path',
        'warehouse_signed_at',
    ];

    protected $appends = [
        'requester_signature_url',
        'manager_signature_url',
        'inventory_signature_url',
        'warehouse_signature_url',
    ];

    protected function casts(): array
    {
        return [
            'requested_on' => 'date',
            'declaration_at' => 'datetime',
            'request_type' => RequestType::class,
            'status' => RequisitionStatus::class,
            'requester_signed_at' => 'datetime',
            'manager_signed_at' => 'datetime',
            'inventory_signed_at' => 'datetime',
            'warehouse_signed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RequisitionEvent::class)->latest();
    }

    public function getRequesterSignatureUrlAttribute(): ?string
    {
        return app(SignatureService::class)->url($this->requester_signature_path);
    }

    public function getManagerSignatureUrlAttribute(): ?string
    {
        return app(SignatureService::class)->url($this->manager_signature_path);
    }

    public function getInventorySignatureUrlAttribute(): ?string
    {
        return app(SignatureService::class)->url($this->inventory_signature_path);
    }

    public function getWarehouseSignatureUrlAttribute(): ?string
    {
        return app(SignatureService::class)->url($this->warehouse_signature_path);
    }

    public function canPrintPickList(): bool
    {
        return in_array($this->status, [
            RequisitionStatus::ReadyForWarehouse,
            RequisitionStatus::PendingInventoryChangeReview,
            RequisitionStatus::Released,
        ], true);
    }

    public function canPrintFinal(): bool
    {
        return $this->status === RequisitionStatus::Released;
    }
}
