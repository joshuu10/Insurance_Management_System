<?php

namespace App\Models;

use App\Support\Uuid;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'subject_type', 'subject_id', 'purpose', 'issuer',
        'original_filename', 'mime_type', 'size_bytes', 'storage_disk', 'storage_path',
        'content_hash', 'classification', 'status', 'uploaded_by',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->id ??= Uuid::v7());
    }
}
