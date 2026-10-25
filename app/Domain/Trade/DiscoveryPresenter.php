<?php

namespace App\Domain\Trade;

use App\Domain\Catalog\CatalogItemImage;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

final class DiscoveryPresenter
{
    public function __construct(private readonly WflCalculator $calculator) {}

    /** @return array<string, mixed> */
    public function listing(Listing $listing): array
    {
        $version = $listing->latestVersion;
        $items = $version?->items ?? collect();
        $names = static fn (string $side): string => $items->where('side', $side)->map(
            fn ($item): string => $item->catalogItem->canonical_name.($item->quantity > 1 ? ' ×'.$item->quantity : '')
        )->join(' + ') ?: 'Value unavailable';
        $threshold = (float) (json_decode((string) DB::table('system_settings')->where('key', 'fair_threshold_percent')->value('value'), true) ?? 10);
        $snapshotVersion = $version?->value_snapshot_id
            ? DB::table('value_snapshots')->where('id', $version->value_snapshot_id)->value('version')
            : null;
        $wfl = $this->calculator->calculate(
            $this->calculatorRows($items->where('side', 'offered')),
            $this->calculatorRows($items->where('side', 'wanted')),
            $threshold,
            $snapshotVersion,
            'listing owner',
        );

        return [
            'id' => $listing->public_id,
            'alias' => 'Angler '.str_pad((string) (crc32($listing->public_id) % 100), 2, '0', STR_PAD_LEFT),
            'offered' => $names('offered'),
            'wanted' => $names('wanted'),
            'offeredThumbnailUrl' => $this->thumbnailUrl($items->firstWhere('side', 'offered')?->catalogItem),
            'wantedThumbnailUrl' => $this->thumbnailUrl($items->firstWhere('side', 'wanted')?->catalogItem),
            'value' => $version?->offered_value,
            'wantedValue' => $version?->wanted_value,
            'age' => $listing->published_at?->diffForHumans(short: true),
            'status' => strtolower($wfl->status->value),
            'snapshotVersion' => $snapshotVersion,
            'wfl' => $wfl->toArray(),
        ];
    }

    private function thumbnailUrl(mixed $item): string
    {
        return CatalogItemImage::url($item?->thumbnail_path, $item?->category?->key ?? 'other');
    }

    /** @return array{version: string, ageLabel: string, sourceTimestamp: ?string}|null */
    public function activeSnapshot(): ?array
    {
        $snapshot = DB::table('value_snapshots')->whereNotNull('published_at')->orderByDesc('published_at')->orderByDesc('id')->first();
        if (! $snapshot) {
            return null;
        }

        $publishedAt = now()->parse($snapshot->published_at);

        return [
            'version' => $snapshot->version,
            'ageLabel' => 'Published '.$publishedAt->diffForHumans(),
            'sourceTimestamp' => $snapshot->source_timestamp ? now()->parse($snapshot->source_timestamp)->toIso8601String() : null,
        ];
    }

    /** @return list<array{id: string, quantity: int, unit_value: ?float}> */
    private function calculatorRows(iterable $items): array
    {
        return collect($items)->map(fn ($item): array => [
            'id' => $item->catalogItem->public_id,
            'quantity' => (int) $item->quantity,
            'unit_value' => $item->unit_value === null ? null : (float) $item->unit_value,
        ])->values()->all();
    }
}
