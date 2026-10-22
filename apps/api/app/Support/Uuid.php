<?php

namespace App\Support;

use Illuminate\Support\Str;

final class Uuid
{
    public static function v7(): string
    {
        // Prefer UUID v7 when available (PHP 8.4+ / Ramsey); fall back to ULID-ordered UUID string.
        if (method_exists(Str::class, 'uuid7')) {
            return (string) Str::uuid7();
        }

        return (string) Str::ulid();
    }
}
