<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'organization_id',
        'name',
        'code',
        'city',
        'region',
        'is_head_office',
    ];

    protected function casts(): array
    {
        return [
            'is_head_office' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Branch $branch): void {
            if (! $branch->id) {
                $branch->id = Uuid::v7();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
