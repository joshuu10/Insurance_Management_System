<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cancellation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'policy_id', 'status', 'reason', 'effective_from',
        'insurer_confirmation_ref', 'refund_amount', 'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'refund_amount' => 'decimal:2',
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
