<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'reference', 'organization_id', 'memo', 'entry_date', 'status', 'source', 'source_id',
        'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->id ??= Uuid::v7());
    }

    public function delete(): ?bool
    {
        throw new \RuntimeException('Posted journals cannot be hard-deleted. Void/reverse instead.');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
