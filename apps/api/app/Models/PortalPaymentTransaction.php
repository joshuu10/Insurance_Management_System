<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalPaymentTransaction extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'customer_id', 'policy_id', 'premium_instruction_id', 'reference',
        'gateway', 'status', 'amount', 'currency', 'receipt_number', 'gateway_payload',
        'fabricated_live_confirmation', 'notice', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_payload' => 'array',
            'fabricated_live_confirmation' => 'boolean',
            'confirmed_at' => 'datetime',
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

    public function premiumInstruction(): BelongsTo
    {
        return $this->belongsTo(PremiumInstruction::class);
    }
}
