<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Suspension extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'lifted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $suspension) => $suspension->public_id ??= (string) Str::uuid());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('lifted_at')->where('starts_at', '<=', now())
            ->where(fn (Builder $inner) => $inner->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
