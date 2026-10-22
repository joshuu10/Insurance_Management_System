<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'legal_name',
        'trading_name',
        'intermediary_type',
        'licence_number',
        'licence_category',
        'licence_issued_on',
        'licence_expires_on',
        'regulatory_status',
        'timezone',
        'currency',
        'locale',
        'onboarding_completed',
        'settings',
        'saas_tenant_id',
        'branding',
    ];

    protected function casts(): array
    {
        return [
            'licence_issued_on' => 'date',
            'licence_expires_on' => 'date',
            'onboarding_completed' => 'boolean',
            'settings' => 'array',
            'branding' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $org): void {
            if (! $org->id) {
                $org->id = Uuid::v7();
            }
        });
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
