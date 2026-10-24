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

    public function scopeWithCurrentItem(Builder $query, string $side, int $catalogItemId): Builder
    {
        return $query->whereExists(function ($items) use ($side, $catalogItemId): void {
            $items->selectRaw('1')
                ->from('listing_versions')
                ->join('listing_version_items', 'listing_version_items.listing_version_id', '=', 'listing_versions.id')
                ->whereColumn('listing_versions.listing_id', 'listings.id')
                ->whereColumn('listing_versions.version', 'listings.current_version')
                ->where('listing_version_items.side', $side)
                ->where('listing_version_items.catalog_item_id', $catalogItemId);
        });
    }

    public function scopeWithCurrentCategory(Builder $query, int $categoryId): Builder
    {
        return $query->whereExists(function ($items) use ($categoryId): void {
            $items->selectRaw('1')
                ->from('listing_versions')
                ->join('listing_version_items', 'listing_version_items.listing_version_id', '=', 'listing_versions.id')
                ->join('catalog_items', 'catalog_items.id', '=', 'listing_version_items.catalog_item_id')
                ->whereColumn('listing_versions.listing_id', 'listings.id')
                ->whereColumn('listing_versions.version', 'listings.current_version')
                ->where('catalog_items.category_id', $categoryId);
        });
    }

    public function scopeWithCurrentOfferedValue(Builder $query, ?int $minimum, ?int $maximum): Builder
    {
        return $query->whereExists(function ($versions) use ($minimum, $maximum): void {
            $versions->selectRaw('1')
                ->from('listing_versions')
                ->whereColumn('listing_versions.listing_id', 'listings.id')
                ->whereColumn('listing_versions.version', 'listings.current_version')
                ->when($minimum !== null, fn ($query) => $query->where('listing_versions.offered_value', '>=', $minimum))
                ->when($maximum !== null, fn ($query) => $query->where('listing_versions.offered_value', '<=', $maximum));
        });
    }
}
