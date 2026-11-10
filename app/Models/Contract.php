<?php

namespace App\Models;

use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'expires_at' => 'immutable_datetime', 'client_signed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime', 'voided_at' => 'immutable_datetime',
            'retention_until' => 'immutable_datetime', 'legal_hold' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class)->orderBy('id');
    }

    public function mailDeliveries(): HasMany
    {
        return $this->hasMany(MailDelivery::class);
    }

    public function pecReceipts(): HasMany
    {
        return $this->hasMany(PecReceipt::class);
    }
}
