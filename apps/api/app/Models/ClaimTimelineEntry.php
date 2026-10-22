<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;

class ClaimTimelineEntry extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'claim_id', 'organization_id', 'event_type', 'message', 'visibility', 'actor_id', 'payload', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $m): void {
            $m->id ??= Uuid::v7();
            $m->created_at ??= now();
        });
    }
}
