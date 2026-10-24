<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Listing extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $listing) => $listing->public_id ??= (string) Str::uuid());
    }

    public function scopeDiscoverable(Builder $query, ?int $viewerId = null): Builder
    {
        return $query->where('status', 'active')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($viewerId, fn (Builder $query) => $query->where('user_id', '!=', $viewerId))
            ->orderByDesc('published_at')->orderByDesc('id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ListingVersion::class)->ofMany('version','max');
    }
}
