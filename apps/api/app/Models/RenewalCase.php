<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenewalCase extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'policy_id', 'status', 'days_to_expiry', 'notified_at',
        'renewal_quote_id', 'renewed_policy_id', 'notes',
    ];

    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
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
