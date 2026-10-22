<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionReceivable extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'policy_id', 'carrier_id', 'commission_schedule_id', 'status',
        'expected_amount', 'confirmed_amount', 'received_amount', 'currency', 'basis', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'confirmed_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->id ??= Uuid::v7());
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }
}
