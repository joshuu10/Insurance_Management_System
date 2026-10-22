<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'provider_key', 'display_name', 'status', 'is_platform_level',
        'config', 'credentials_ref', 'last_health_check_at', 'last_health_status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_platform_level' => 'boolean',
            'last_health_check_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->id ??= Uuid::v7());
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(IntegrationSyncRun::class);
    }
}
