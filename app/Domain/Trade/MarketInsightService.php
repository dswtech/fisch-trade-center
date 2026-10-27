<?php

namespace App\Domain\Trade;

use App\Domain\Catalog\CatalogItemImage;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

final class MarketInsightService
{
    /** @return array{activeListingCount: int, mostOffered: array, mostWanted: array} */
    public function summary(): array
    {
        return [
            'activeListingCount' => Listing::discoverable()->reorder()->count(),
            'mostOffered' => $this->rankedItems('offered'),
            'mostWanted' => $this->rankedItems('wanted'),
        ];
    }

    /** @return list<array{id: string, name: string, thumbnailUrl: string, listingCount: int}> */
    private function rankedItems(string $side): array
    {
        return DB::table('listing_version_items as item')
            ->join('listing_versions as version', 'version.id', '=', 'item.listing_version_id')
            ->join('listings as listing', 'listing.id', '=', 'version.listing_id')
            ->join('catalog_items as catalog', 'catalog.id', '=', 'item.catalog_item_id')
            ->join('catalog_categories as category', 'category.id', '=', 'catalog.category_id')
            ->where('listing.status', 'active')
            ->where(fn ($query) => $query->whereNull('listing.expires_at')->orWhere('listing.expires_at', '>', now()))
            ->whereColumn('version.version', 'listing.current_version')
            ->where('item.side', $side)
            ->select([
                'catalog.public_id as id',
                'catalog.canonical_name as name',
                'catalog.thumbnail_path',
                'category.key as category_key',
                DB::raw('COUNT(DISTINCT listing.id) as listing_count'),
            ])
            ->groupBy('catalog.id', 'catalog.public_id', 'catalog.canonical_name', 'catalog.thumbnail_path', 'category.key')
            ->orderByDesc('listing_count')
            ->orderBy('catalog.canonical_name')
            ->limit(5)
            ->get()
            ->map(fn ($item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'thumbnailUrl' => CatalogItemImage::url($item->thumbnail_path, $item->category_key),
                'listingCount' => (int) $item->listing_count,
            ])->all();
    }
}
