<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PecReceipt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime'];
    }
}
