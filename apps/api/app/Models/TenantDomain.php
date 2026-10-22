<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;

class TenantDomain extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'saas_tenant_id', 'hostname', 'is_primary', 'verified', 'verification_token',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->id ??= Uuid::v7());
    }
}
