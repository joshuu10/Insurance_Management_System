<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'branch_id', 'type', 'display_name', 'email', 'phone_e164',
        'preferred_locale', 'kyc_status', 'identity_verification_status', 'identity_verified_at',
        'national_id_last4', 'metadata', 'owner_user_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'identity_verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->id ??= Uuid::v7());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function portalUser(): HasOne
    {
        return $this->hasOne(User::class, 'customer_id');
    }
}
