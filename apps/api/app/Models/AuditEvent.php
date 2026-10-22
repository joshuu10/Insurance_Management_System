<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'organization_id',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'before',
        'after',
        'ip_address',
        'user_agent',
        'correlation_id',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Audit events are append-only. Updates and deletes are blocked at the model layer.
     */
    public function delete(): ?bool
    {
        throw new \RuntimeException('Audit events cannot be deleted.');
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \RuntimeException('Audit events cannot be updated.');
    }
}
