<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrier extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'legal_name',
        'trading_name',
        'country_code',
        'status',
        'is_illustrative',
        'disclaimer',
        'data_source',
    ];

    protected function casts(): array
    {
        return [
            'is_illustrative' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Carrier $carrier): void {
            if (! $carrier->id) {
                $carrier->id = Uuid::v7();
            }
        });
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(CarrierAppointment::class);
    }
}

