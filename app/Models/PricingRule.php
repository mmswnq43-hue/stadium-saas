<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    protected $fillable = [
        'tenant_id', 'field_id', 'name', 'type',
        'days_of_week', 'start_time', 'end_time',
        'date_from', 'date_to', 'price', 'price_type', 'priority', 'is_active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'price'        => 'decimal:2',
        'is_active'    => 'boolean',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
