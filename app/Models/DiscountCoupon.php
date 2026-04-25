<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountCoupon extends Model
{
    protected $fillable = [
        'tenant_id', 'code', 'description', 'type', 'value',
        'min_booking_amount', 'max_discount_amount',
        'usage_limit', 'usage_count', 'usage_limit_per_user',
        'valid_from', 'valid_until', 'is_active',
    ];

    protected $casts = [
        'value'               => 'decimal:2',
        'min_booking_amount'  => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'valid_from'          => 'date',
        'valid_until'         => 'date',
        'is_active'           => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
