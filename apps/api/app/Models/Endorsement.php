<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Endorsement extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'policy_id', 'reference', 'type', 'status', 'description',
        'requested_changes', 'adjustment_premium', 'insurer_approval_ref', 'effective_from', 'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_changes' => 'array',
            'adjustment_premium' => 'decimal:2',
            'effective_from' => 'datetime',
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
