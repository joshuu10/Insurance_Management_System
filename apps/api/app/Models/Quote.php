<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'reference', 'organization_id', 'branch_id', 'customer_id', 'lead_id', 'carrier_id',
        'carrier_product_id', 'product_version_id', 'owner_user_id', 'status', 'premium_classification',
        'premium_amount', 'currency', 'risk_summary', 'answers', 'caveats', 'valid_until', 'version',
    ];

    protected function casts(): array
    {
        return [
            'premium_amount' => 'decimal:2',
            'risk_summary' => 'array',
            'answers' => 'array',
            'valid_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $m): void {
            $m->id ??= Uuid::v7();
            $m->reference ??= 'Q-'.strtoupper(substr(Uuid::v7(), 0, 8));
        });
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
