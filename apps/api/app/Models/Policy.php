<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Policy extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'branch_id', 'customer_id', 'carrier_id', 'carrier_product_id',
        'quote_id', 'application_id', 'owner_user_id', 'carrier_policy_number', 'cover_note_number',
        'status', 'verification_status', 'verification_reference', 'effective_from', 'effective_to',
        'sum_insured', 'premium_amount', 'currency', 'insured_risks', 'exclusions', 'schedule',
        'source_document_ref', 'provenance', 'intake_notes', 'version',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'sum_insured' => 'decimal:2',
            'premium_amount' => 'decimal:2',
            'insured_risks' => 'array',
            'exclusions' => 'array',
            'schedule' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->id ??= Uuid::v7());
    }

    public function delete(): ?bool
    {
        throw new \RuntimeException('Issued policies cannot be hard-deleted. Use cancellation/status correction.');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function carrierProduct(): BelongsTo
    {
        return $this->belongsTo(CarrierProduct::class);
    }

    public function endorsements(): HasMany
    {
        return $this->hasMany(Endorsement::class);
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(Cancellation::class);
    }

    public function renewalCases(): HasMany
    {
        return $this->hasMany(RenewalCase::class);
    }
}
