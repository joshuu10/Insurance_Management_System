<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarrierProduct extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'carrier_id', 'product_class_id', 'name', 'code', 'status', 'requires_insurer_rating',
    ];

    protected function casts(): array
    {
        return ['requires_insurer_rating' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->id ??= Uuid::v7());
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function productClass(): BelongsTo
    {
        return $this->belongsTo(ProductClass::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class);
    }
}
