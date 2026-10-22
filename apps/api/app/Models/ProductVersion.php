<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVersion extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'carrier_product_id', 'version_label', 'effective_from', 'effective_to',
        'questionnaire_schema', 'required_documents', 'disclosures', 'is_current',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'questionnaire_schema' => 'array',
            'required_documents' => 'array',
            'is_current' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->id ??= Uuid::v7());
    }

    public function carrierProduct(): BelongsTo
    {
        return $this->belongsTo(CarrierProduct::class);
    }
}
