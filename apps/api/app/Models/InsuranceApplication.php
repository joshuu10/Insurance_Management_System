<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsuranceApplication extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'reference', 'organization_id', 'customer_id', 'quote_id', 'carrier_id',
        'carrier_product_id', 'owner_user_id', 'status', 'payload', 'disclosure_acknowledged',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'disclosure_acknowledged' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $m): void {
            $m->id ??= Uuid::v7();
            $m->reference ??= 'A-'.strtoupper(substr(Uuid::v7(), 0, 8));
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
