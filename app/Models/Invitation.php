<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'claimed_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'last_sent_at' => 'immutable_datetime'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function isUsable(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture();
    }
}
