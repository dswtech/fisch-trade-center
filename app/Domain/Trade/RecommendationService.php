<?php

namespace App\Domain\Trade;

use App\Models\Listing;
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
                'catalog_item_values.unit_value', 'catalog_item_values.offer_range',
            ]);

        $missing = $inventory->whereNull('unit_value')->pluck('canonical_name')->values()->all();
        if ($target === null || $target <= 0 || $inventory->whereNotNull('unit_value')->isEmpty()) {
            return ['snapshotId' => $this->snapshotPublicId($listing->value_snapshot_id), 'targetValue' => $target, 'missingItems' => $missing, 'candidates' => []];
        }

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

        $threshold = (float) (DB::table('system_settings')->where('key', 'fair_threshold_percent')->value('value') ?? 10);
        $results = collect($beam)->filter(fn (array $candidate): bool => $candidate['items'] !== [])->unique(fn (array $candidate): string => $this->key($candidate))->take((int) config('phase_two.recommendation_limit', 10))->map(function (array $candidate) use ($target, $threshold): array {
            $delta = $target - $candidate['total'];
            $percent = $candidate['total'] > 0 ? ($delta / $candidate['total']) * 100 : null;
            $status = $percent === null ? 'unavailable' : (abs($percent) <= $threshold ? 'fair' : ($percent > 0 ? 'win' : 'lose'));
            return [...$candidate, 'receiveTotal' => $target, 'delta' => $delta, 'deltaPercent' => $percent, 'status' => $status, 'perspective' => 'viewer'];
        })->values()->all();

        return ['snapshotId' => $this->snapshotPublicId($listing->value_snapshot_id), 'targetValue' => $target, 'missingItems' => $missing, 'candidates' => $results];
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
