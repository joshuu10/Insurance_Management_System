<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Claim extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'reference', 'organization_id', 'policy_id', 'customer_id', 'carrier_id', 'owner_user_id',
        'status', 'incident_at', 'incident_location', 'description', 'police_reference', 'declared_amount',
        'insurer_approved_amount', 'currency', 'urgency', 'coverage_provisional_notice', 'fraud_review_flag',
        'staff_notes', 'submitted_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'incident_at' => 'datetime',
            'declared_amount' => 'decimal:2',
            'insurer_approved_amount' => 'decimal:2',
            'coverage_provisional_notice' => 'boolean',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(ClaimTimelineEntry::class)->orderBy('created_at');
    }
}
