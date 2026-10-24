<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $rules = [
            'offeredItem' => ['nullable', 'uuid'],
            'wantedItem' => ['nullable', 'uuid'],
            'category' => ['nullable', 'uuid'],
            'minValue' => ['nullable', 'integer', 'min:0'],
            'maxValue' => ['nullable', 'integer', 'min:0'],
            'freshness' => ['nullable', 'in:hour,day,week'],
            'cursor' => ['nullable', 'string', 'max:500'],
        ];
        if ($request->filled('minValue')) {
            $rules['maxValue'][] = 'gte:minValue';
        }
        $filters = $request->validate($rules);

        $offeredId = $this->catalogItemId($filters['offeredItem'] ?? null);
        $wantedId = $this->catalogItemId($filters['wantedItem'] ?? null);
        $categoryId = $this->categoryId($filters['category'] ?? null);
        $hasUnknownFilter = (($filters['offeredItem'] ?? null) && ! $offeredId)
            || (($filters['wantedItem'] ?? null) && ! $wantedId)
            || (($filters['category'] ?? null) && ! $categoryId);

        $query = Listing::discoverable($request->user()?->id)
            ->when($hasUnknownFilter, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($offeredId, fn ($query) => $query->withCurrentItem('offered', $offeredId))
            ->when($wantedId, fn ($query) => $query->withCurrentItem('wanted', $wantedId))
            ->when($categoryId, fn ($query) => $query->withCurrentCategory($categoryId))
            ->when(isset($filters['minValue']) || isset($filters['maxValue']), fn ($query) => $query->withCurrentOfferedValue($filters['minValue'] ?? null, $filters['maxValue'] ?? null))
            ->when(($filters['freshness'] ?? null) === 'hour', fn ($query) => $query->where('published_at', '>=', now()->subHour()))
            ->when(($filters['freshness'] ?? null) === 'day', fn ($query) => $query->where('published_at', '>=', now()->subDay()))
            ->when(($filters['freshness'] ?? null) === 'week', fn ($query) => $query->where('published_at', '>=', now()->subWeek()))
            ->with(['latestVersion.items.catalogItem']);

        $page = $query->cursorPaginate(24)->withQueryString();
        $listings = collect($page->items())->map(function (Listing $listing): array {
            $version = $listing->latestVersion;
            $names = fn (string $side): string => $version?->items->where('side', $side)->map(
                fn ($item): string => $item->catalogItem->canonical_name.($item->quantity > 1 ? ' ×'.$item->quantity : '')
            )->join(' + ') ?: 'Value unavailable';

            return [
                'id' => $listing->public_id,
                'alias' => 'Angler '.str_pad((string) (crc32($listing->public_id) % 100), 2, '0', STR_PAD_LEFT),
                'offered' => $names('offered'),
                'wanted' => $names('wanted'),
                'value' => $version?->offered_value,
                'age' => $listing->published_at?->diffForHumans(short: true),
                'status' => 'unavailable',
            ];
        });

        return Inertia::render('discovery', [
            'listings' => $listings,
            'nextCursor' => $page->nextCursor()?->encode(),
            'filters' => collect($filters)->except('cursor'),
            'filterOptions' => [
                'items' => DB::table('catalog_items')->where('is_active', true)->orderBy('canonical_name')->get(['public_id as id', 'canonical_name as name']),
                'categories' => DB::table('catalog_categories')->where('is_active', true)->orderBy('name')->get(['public_id as id', 'name']),
            ],
        ]);
    }

    private function catalogItemId(?string $publicId): ?int
    {
        return $publicId ? DB::table('catalog_items')->where('public_id', $publicId)->where('is_active', true)->value('id') : null;
    }

    private function categoryId(?string $publicId): ?int
    {
        return $publicId ? DB::table('catalog_categories')->where('public_id', $publicId)->where('is_active', true)->value('id') : null;
    }
}
