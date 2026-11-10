<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'sent_at' => 'immutable_datetime'];
    }
}
