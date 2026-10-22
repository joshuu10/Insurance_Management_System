<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;

class IntegrationSyncRun extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'integration_id', 'direction', 'status', 'request_meta', 'response_meta', 'error_message', 'attempt',
    ];

    protected function casts(): array
    {
        return [
            'request_meta' => 'array',
            'response_meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->id ??= Uuid::v7());
    }
}
