<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class CatalogMetadataImport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['source_timestamp' => 'datetime', 'imported_at' => 'datetime', 'validation_errors' => 'array', 'payload' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $import) => $import->public_id ??= (string) Str::uuid());
    }
}
