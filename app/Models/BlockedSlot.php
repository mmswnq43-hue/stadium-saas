<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedSlot extends Model
{
    protected $fillable = [
        'tenant_id', 'field_id', 'date',
        'start_time', 'end_time', 'reason', 'is_full_day',
    ];

    protected $casts = [
        'date'        => 'date',
        'is_full_day' => 'boolean',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
