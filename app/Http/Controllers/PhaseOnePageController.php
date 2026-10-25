<?php
namespace App\Http\Controllers;

use App\Domain\Catalog\CatalogItemImage;
use App\Domain\Trade\WflCalculator;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PhaseOnePageController extends Controller
{
    public function catalog(): Response
    {
        return Inertia::render('workspace', ['section' => 'catalog', 'items' => $this->catalogItems()]);
    }

    public function inventory(Request $request): Response
    {
        return Inertia::render('workspace', [
            'section' => 'inventory',
            'items' => $this->catalogItems(),
            'entries' => $this->inventoryEntries($request),
        ]);
    }

    public function listings(Request $request): Response
    {
        $rows = Listing::where('user_id', $request->user()->id)->with(['latestVersion.items.catalogItem.category'])->orderByDesc('id')->get()
            ->map(fn (Listing $listing): array => [
                'id' => $listing->public_id,
                'status' => $listing->status,
                'version' => $listing->current_version,
                'expiresAt' => $listing->expires_at?->toIso8601String(),
                'offered' => $this->sideNames($listing, 'offered'),
                'wanted' => $this->sideNames($listing, 'wanted'),
                'offeredThumbnailUrl' => $this->sideThumbnail($listing, 'offered'),
                'wantedThumbnailUrl' => $this->sideThumbnail($listing, 'wanted'),
            ]);

        return Inertia::render('workspace', ['section' => 'listings', 'listings' => $rows]);
    }

    public function listingCreate(Request $request): Response
    {
        return Inertia::render('workspace', ['section' => 'listing-create', 'items' => $this->catalogItems(), 'entries' => $this->inventoryEntries($request)]);
    }

    public function listingEdit(Request $request, string $listing): Response
    {
        $owned = Listing::where('public_id', $listing)->where('user_id', $request->user()->id)->with(['latestVersion.items.catalogItem.category'])->firstOrFail();
        abort_unless($owned->status === 'active' && ($owned->expires_at === null || $owned->expires_at->isFuture()), 409, 'Only an active, unexpired listing can be edited.');

        return Inertia::render('workspace', [
            'section' => 'listing-edit',
            'items' => $this->catalogItems(),
            'entries' => $this->inventoryEntries($request),
            'listings' => [[
                'id' => $owned->public_id,
                'status' => $owned->status,
                'version' => $owned->current_version,
                'expiresAt' => $owned->expires_at?->toIso8601String(),
                'selected' => [
                    'offered' => $owned->latestVersion->items->where('side', 'offered')->map(fn ($item): array => ['id' => $item->inventory_entry_id, 'quantity' => $item->quantity])->values(),
                    'wanted' => $owned->latestVersion->items->where('side', 'wanted')->map(fn ($item): array => ['id' => $item->catalogItem->public_id, 'quantity' => $item->quantity])->values(),
                ],
            ]],
        ]);
    }

    public function simulator(Request $request, WflCalculator $calculator): Response
    {
        $data = $request->validate([
            'give' => ['nullable', 'array', 'min:1'], 'give.*.catalogItemId' => ['required', 'uuid'], 'give.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'receive' => ['nullable', 'array', 'min:1'], 'receive.*.catalogItemId' => ['required', 'uuid'], 'receive.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);
        $snapshot = DB::table('value_snapshots')->whereNotNull('published_at')->orderByDesc('published_at')->orderByDesc('id')->first();
        $result = null;
        if (($data['give'] ?? []) !== [] && ($data['receive'] ?? []) !== []) {
            $threshold = (float) (json_decode((string) DB::table('system_settings')->where('key', 'fair_threshold_percent')->value('value'), true) ?? 10);
            $result = $calculator->calculate(
                $this->simulationRows($data['give'], $snapshot?->id),
                $this->simulationRows($data['receive'], $snapshot?->id),
                $threshold,
                $snapshot?->version,
                'viewer',
            )->toArray();
        }

        return Inertia::render('workspace', ['section' => 'simulator', 'items' => $this->catalogItems(), 'result' => $result, 'selected' => $data]);
    }

    public function profile(Request $request): Response
    {
        return Inertia::render('workspace', ['section' => 'profile', 'profile' => DB::table('player_profiles')->where('user_id', $request->user()->id)->first(['usn', 'ign'])]);
    }

    private function catalogItems()
    {
        $snapshotId = DB::table('value_snapshots')->whereNotNull('published_at')->orderByDesc('published_at')->orderByDesc('id')->value('id');

        return DB::table('catalog_items')->join('catalog_categories', 'catalog_categories.id', '=', 'catalog_items.category_id')
            ->leftJoin('rods', 'rods.id', '=', 'catalog_items.rod_id')
            ->leftJoin('catalog_item_values', fn ($join) => $join->on('catalog_item_values.catalog_item_id', '=', 'catalog_items.id')->where('catalog_item_values.snapshot_id', $snapshotId ?? 0))
            ->where('catalog_items.is_active', true)->where('catalog_categories.is_active', true)
            ->whereIn('catalog_categories.key', array_keys((array) config('catalog.categories')))->orderBy('canonical_name')
            ->get(['catalog_items.public_id as id', 'canonical_name as name', 'catalog_categories.name as category', 'catalog_categories.key as categoryKey', 'catalog_items.thumbnail_path as thumbnailPath', 'rods.name as rodName', 'catalog_item_values.unit_value as value', 'catalog_item_values.offer_range as offerRange'])
            ->map(fn ($item): array => [
                'id' => $item->id, 'name' => $item->name, 'category' => $item->category,
                'rodName' => $item->rodName ?? ($item->categoryKey === 'rod_skin' ? 'Undefined Rod' : null),
                'thumbnailUrl' => CatalogItemImage::url($item->thumbnailPath, $item->categoryKey),
                'value' => $item->value === null ? null : (float) $item->value, 'offerRange' => $item->offerRange,
            ]);
    }

    private function inventoryEntries(Request $request)
    {
        return DB::table('inventory_entries')->join('catalog_items', 'catalog_items.id', '=', 'inventory_entries.catalog_item_id')
            ->join('catalog_categories', 'catalog_categories.id', '=', 'catalog_items.category_id')
            ->where('user_id', $request->user()->id)->whereNull('archived_at')->orderBy('canonical_name')
            ->get(['inventory_entries.public_id as id', 'catalog_items.public_id as catalogItemId', 'canonical_name as name', 'quantity', 'catalog_items.thumbnail_path as thumbnailPath', 'catalog_categories.key as categoryKey'])
            ->map(fn ($entry): array => [
                'id' => $entry->id, 'catalogItemId' => $entry->catalogItemId, 'name' => $entry->name,
                'quantity' => $entry->quantity, 'thumbnailUrl' => CatalogItemImage::url($entry->thumbnailPath, $entry->categoryKey),
            ]);
    }

    /** @param list<array{catalogItemId: string, quantity: int}> $rows */
    private function simulationRows(array $rows, ?int $snapshotId): array
    {
        return collect($rows)->map(function (array $row) use ($snapshotId): array {
            $item = DB::table('catalog_items')->where('public_id', $row['catalogItemId'])->where('is_active', true)->first();
            abort_unless($item, 422, 'Simulator contains an inactive or unknown item.');
            $value = $snapshotId ? DB::table('catalog_item_values')->where('snapshot_id', $snapshotId)->where('catalog_item_id', $item->id)->value('unit_value') : null;

            return ['id' => $item->public_id, 'quantity' => (int) $row['quantity'], 'unit_value' => $value === null ? null : (float) $value];
        })->all();
    }

    private function sideNames(Listing $listing, string $side): string
    {
        return $listing->latestVersion?->items->where('side', $side)->map(fn ($item): string => $item->catalogItem->canonical_name.($item->quantity > 1 ? ' ×'.$item->quantity : ''))->join(' + ') ?: '—';
    }

    private function sideThumbnail(Listing $listing, string $side): string
    {
        $item = $listing->latestVersion?->items->firstWhere('side', $side)?->catalogItem;

        return CatalogItemImage::url($item?->thumbnail_path, $item?->category?->key ?? 'other');
    }
}
