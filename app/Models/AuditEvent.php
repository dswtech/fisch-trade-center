<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class AuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'before_state' => 'array', 'after_state' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $event) => $event->public_id ??= (string) Str::uuid());
    }
}
