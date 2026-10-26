<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Services\SignatureService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'department_id', 'signature_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    protected $appends = [
        'signature_url',
        'has_signature',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requisitions(): HasMany
    {
        return $this->hasMany(Requisition::class, 'requester_id');
    }

    public function getSignatureUrlAttribute(): ?string
    {
        return app(SignatureService::class)->url($this->signature_path);
    }

    public function getHasSignatureAttribute(): bool
    {
        return filled($this->signature_path);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isRequester(): bool
    {
        return $this->role === UserRole::Requester;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function isInventoryManager(): bool
    {
        return $this->role === UserRole::InventoryManager;
    }

    public function isWarehouse(): bool
    {
        return $this->role === UserRole::Warehouse;
    }
}
