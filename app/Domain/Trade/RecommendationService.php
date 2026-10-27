<?php

namespace App\Domain\Trade;

use App\Domain\Catalog\CatalogItemImage;
use App\Models\Listing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RecommendationService
{
    public function recommend(int $userId, Listing $listing): array
    {
        abort_unless($listing->status === 'active' && (! $listing->expires_at || $listing->expires_at->isFuture()), 409, 'Listing is no longer active.');

        $version = DB::table('listing_versions')
            ->where('listing_id', $listing->id)
            ->where('version', $listing->current_version)
            ->first();
        abort_unless($version, 409, 'Listing version is unavailable.');

        $target = $version->offered_value === null ? null : (float) $version->offered_value;
        $inventory = DB::table('inventory_entries')
            ->join('catalog_items', 'catalog_items.id', '=', 'inventory_entries.catalog_item_id')
            ->join('catalog_categories', 'catalog_categories.id', '=', 'catalog_items.category_id')
            ->leftJoin('catalog_item_values', function ($join) use ($listing): void {
                $join->on('catalog_item_values.catalog_item_id', '=', 'inventory_entries.catalog_item_id')
                    ->where('catalog_item_values.snapshot_id', '=', $listing->value_snapshot_id);
            })
            ->where('inventory_entries.user_id', $userId)
            ->whereNull('inventory_entries.archived_at')
            ->where('inventory_entries.quantity', '>', 0)
            ->orderBy('inventory_entries.id')
            ->get([
                'inventory_entries.id', 'inventory_entries.public_id', 'inventory_entries.quantity',
                'catalog_items.public_id as catalog_public_id', 'catalog_items.canonical_name',
                'catalog_items.thumbnail_path', 'catalog_categories.key as category_key',
                'catalog_item_values.unit_value', 'catalog_item_values.offer_range',
            ]);

        $missing = $inventory->whereNull('unit_value')->pluck('canonical_name')->values()->all();
        $storedThreshold = DB::table('system_settings')->where('key', 'fair_threshold_percent')->value('value');
        $threshold = $storedThreshold === null ? 10.0 : (float) (json_decode((string) $storedThreshold, true) ?? 10);
        $inventoryRows = $inventory->map(fn ($entry): array => [
            'id' => $entry->public_id,
            'inventoryEntryId' => $entry->public_id,
            'name' => $entry->canonical_name,
            'thumbnailUrl' => CatalogItemImage::url($entry->thumbnail_path, $entry->category_key),
            'available' => (int) $entry->quantity,
            'unitValue' => $entry->unit_value === null ? null : (float) $entry->unit_value,
            'offerRange' => $entry->offer_range,
        ])->values()->all();
        if ($target === null || $target <= 0 || $inventory->whereNotNull('unit_value')->isEmpty()) {
            return ['snapshotId' => $this->snapshotPublicId($listing->value_snapshot_id), 'targetValue' => $target, 'missingItems' => $missing, 'inventory' => $inventoryRows, 'fairThresholdPercent' => $threshold, 'candidates' => []];
        }

        $results = $this->rankedCandidates($inventory, $target, $threshold);

        return ['snapshotId' => $this->snapshotPublicId($listing->value_snapshot_id), 'targetValue' => $target, 'missingItems' => $missing, 'inventory' => $inventoryRows, 'fairThresholdPercent' => $threshold, 'candidates' => $results];
    }

    /**
     * Rank the visible discovery listings with one inventory read and one value read.
     *
     * @param Collection<int, Listing> $listings
     * @return array{state: string, results: array}
     */
    public function recommendListings(int $userId, Collection $listings): array
    {
        $inventory = DB::table('inventory_entries')
            ->join('catalog_items', 'catalog_items.id', '=', 'inventory_entries.catalog_item_id')
            ->join('catalog_categories', 'catalog_categories.id', '=', 'catalog_items.category_id')
            ->where('inventory_entries.user_id', $userId)
            ->whereNull('inventory_entries.archived_at')
            ->where('inventory_entries.quantity', '>', 0)
            ->orderBy('inventory_entries.id')
            ->get([
                'inventory_entries.id', 'inventory_entries.public_id', 'inventory_entries.quantity',
                'catalog_items.id as catalog_id', 'catalog_items.public_id as catalog_public_id', 'catalog_items.canonical_name',
                'catalog_items.thumbnail_path', 'catalog_categories.key as category_key',
            ]);

        if ($inventory->isEmpty()) {
            return ['state' => 'empty_inventory', 'results' => []];
        }

        $snapshotIds = $listings->map(fn (Listing $listing) => $listing->latestVersion?->value_snapshot_id)->filter()->unique()->values();
        $values = $snapshotIds->isEmpty() ? collect() : DB::table('catalog_item_values')
            ->whereIn('snapshot_id', $snapshotIds)
            ->whereIn('catalog_item_id', $inventory->pluck('catalog_id'))
            ->get(['snapshot_id', 'catalog_item_id', 'unit_value', 'offer_range'])
            ->keyBy(fn ($value): string => $value->snapshot_id.':'.$value->catalog_item_id);
        $storedThreshold = DB::table('system_settings')->where('key', 'fair_threshold_percent')->value('value');
        $threshold = $storedThreshold === null ? 10.0 : (float) (json_decode((string) $storedThreshold, true) ?? 10);
        $results = [];

        foreach ($listings as $listing) {
            $version = $listing->latestVersion;
            $target = $version?->offered_value === null ? null : (float) $version->offered_value;
            if (! $version?->value_snapshot_id || $target === null || $target <= 0) {
                continue;
            }
            $valuedInventory = $inventory->map(function ($entry) use ($values, $version) {
                $value = $values->get($version->value_snapshot_id.':'.$entry->catalog_id);
                return (object) [...(array) $entry, 'unit_value' => $value?->unit_value, 'offer_range' => $value?->offer_range];
            });
            $candidate = $this->rankedCandidates($valuedInventory, $target, $threshold)[0] ?? null;
            if (! $candidate) {
                continue;
            }
            $results[] = [
                'listingId' => $listing->public_id,
                'items' => $candidate['items'],
                'giveTotal' => $candidate['total'],
                'receiveTotal' => $candidate['receiveTotal'],
                'delta' => $candidate['delta'],
                'deltaPercent' => $candidate['deltaPercent'],
                'status' => $candidate['status'],
                'perspective' => 'viewer',
                '_publishedAt' => $listing->published_at?->getTimestamp() ?? 0,
                '_id' => $listing->id,
            ];
        }

        usort($results, fn (array $a, array $b): int => [abs($a['deltaPercent']), count($a['items']), -$a['_publishedAt'], -$a['_id']] <=> [abs($b['deltaPercent']), count($b['items']), -$b['_publishedAt'], -$b['_id']]);
        $results = array_map(function (array $result): array {
            unset($result['_publishedAt'], $result['_id']);
            return $result;
        }, array_slice($results, 0, 3));

        return ['state' => 'ready', 'results' => $results];
    }

    /** @return list<array<string, mixed>> */
    private function rankedCandidates(Collection $inventory, float $target, float $threshold): array
    {
        $beam = [['items' => [], 'total' => 0.0]];
        $maxStacks = (int) config('phase_two.recommendation_max_stacks', 3);
        $beamWidth = (int) config('phase_two.recommendation_beam_width', 100);
        foreach ($inventory->whereNotNull('unit_value') as $entry) {
            $unit = (float) $entry->unit_value;
            $quantityLimit = min((int) $entry->quantity, max(1, (int) ceil($target / max($unit, 0.01)) + 1));
            $next = $beam;
            foreach ($beam as $candidate) {
                if (count($candidate['items']) >= $maxStacks) {
                    continue;
                }
                for ($quantity = 1; $quantity <= $quantityLimit; $quantity++) {
                    $items = [...$candidate['items'], [
                        'inventoryEntryId' => $entry->public_id,
                        'catalogItemId' => $entry->catalog_public_id,
                        'name' => $entry->canonical_name,
                        'thumbnailUrl' => CatalogItemImage::url($entry->thumbnail_path, $entry->category_key),
                        'quantity' => $quantity,
                        'unitValue' => $unit,
                        'offerRange' => $entry->offer_range,
                    ]];
                    $next[] = ['items' => $items, 'total' => $candidate['total'] + ($unit * $quantity)];
                }
            }
            usort($next, fn (array $a, array $b): int => [abs($a['total'] - $target), count($a['items']), $this->key($a)] <=> [abs($b['total'] - $target), count($b['items']), $this->key($b)]);
            $beam = array_slice($next, 0, $beamWidth);
        }

        return collect($beam)->filter(fn (array $candidate): bool => $candidate['items'] !== [])->unique(fn (array $candidate): string => $this->key($candidate))->take((int) config('phase_two.recommendation_limit', 10))->map(function (array $candidate) use ($target, $threshold): array {
            $delta = $target - $candidate['total'];
            $percent = $candidate['total'] > 0 ? ($delta / $candidate['total']) * 100 : null;
            $status = $percent === null ? 'unavailable' : (abs($percent) <= $threshold ? 'fair' : ($percent > 0 ? 'win' : 'lose'));
            return [...$candidate, 'receiveTotal' => $target, 'delta' => $delta, 'deltaPercent' => $percent, 'status' => $status, 'perspective' => 'viewer'];
        })->values()->all();
    }

    private function key(array $candidate): string
    {
        return collect($candidate['items'])->map(fn (array $item): string => $item['inventoryEntryId'].':'.$item['quantity'])->join('|');
    }

    private function snapshotPublicId(?int $snapshotId): ?string
    {
        return $snapshotId ? DB::table('value_snapshots')->where('id', $snapshotId)->value('public_id') : null;
    }
}
