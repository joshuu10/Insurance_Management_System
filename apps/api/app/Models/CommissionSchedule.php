<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;

class CommissionSchedule extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'carrier_id', 'carrier_product_id', 'name', 'rate_percent',
        'fixed_amount', 'effective_from', 'effective_to', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:4',
            'fixed_amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->id ??= Uuid::v7());
    }
}
