<?php

namespace App\Http\Controllers;

use App\Domain\Trade\DiscoveryPresenter;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request, DiscoveryPresenter $presenter): Response
    {
        $rules = [
            'offeredItem' => ['nullable', 'uuid'],
            'wantedItem' => ['nullable', 'uuid'],
            'category' => ['nullable', 'uuid'],
            'minValue' => ['nullable', 'numeric', 'min:0'],
            'maxValue' => ['nullable', 'numeric', 'min:0'],
            'q' => ['nullable', 'string', 'max:100'],
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
        $fuzzyItemIds = $this->fuzzyCatalogItemIds($filters['q'] ?? null);
        $hasUnknownFilter = (($filters['offeredItem'] ?? null) && ! $offeredId)
            || (($filters['wantedItem'] ?? null) && ! $wantedId)
            || (($filters['category'] ?? null) && ! $categoryId);

        $query = Listing::discoverable($request->user()?->id)
            ->when($hasUnknownFilter, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($offeredId, fn ($query) => $query->withCurrentItem('offered', $offeredId))
            ->when($wantedId, fn ($query) => $query->withCurrentItem('wanted', $wantedId))
            ->when($categoryId, fn ($query) => $query->withCurrentCategory($categoryId))
            ->when(isset($filters['q']), fn ($query) => $fuzzyItemIds === [] ? $query->whereRaw('1 = 0') : $query->withCurrentItems($fuzzyItemIds))
            ->when(isset($filters['minValue']) || isset($filters['maxValue']), fn ($query) => $query->withCurrentOfferedValue($filters['minValue'] ?? null, $filters['maxValue'] ?? null))
            ->when(($filters['freshness'] ?? null) === 'hour', fn ($query) => $query->where('published_at', '>=', now()->subHour()))
            ->when(($filters['freshness'] ?? null) === 'day', fn ($query) => $query->where('published_at', '>=', now()->subDay()))
            ->when(($filters['freshness'] ?? null) === 'week', fn ($query) => $query->where('published_at', '>=', now()->subWeek()))
            ->with(['latestVersion.items.catalogItem.category']);

        $page = $query->cursorPaginate(24)->withQueryString();
        $listings = collect($page->items())->map($presenter->listing(...));

        return Inertia::render('discovery', [
            'listings' => $listings,
            'nextCursor' => $page->nextCursor()?->encode(),
            'filters' => collect($filters)->except('cursor'),
            'snapshot' => $presenter->activeSnapshot(),
            'filterOptions' => [
                'items' => DB::table('catalog_items')->join('catalog_categories', 'catalog_categories.id', '=', 'catalog_items.category_id')->where('catalog_items.is_active', true)->where('catalog_categories.is_active', true)->whereIn('catalog_categories.key', array_keys((array) config('catalog.categories')))->orderBy('canonical_name')->get(['catalog_items.public_id as id', 'canonical_name as name']),
                'categories' => DB::table('catalog_categories')->where('is_active', true)->whereIn('key', array_keys((array) config('catalog.categories')))->orderBy('name')->get(['public_id as id', 'name']),
            ],
        ]);
    }

    private function catalogItemId(?string $publicId): ?int
    {
        return $publicId ? DB::table('catalog_items')->join('catalog_categories', 'catalog_categories.id', '=', 'catalog_items.category_id')->where('catalog_items.public_id', $publicId)->where('catalog_items.is_active', true)->where('catalog_categories.is_active', true)->whereIn('catalog_categories.key', array_keys((array) config('catalog.categories')))->value('catalog_items.id') : null;
    }

    private function categoryId(?string $publicId): ?int
    {
        return $publicId ? DB::table('catalog_categories')->where('public_id', $publicId)->where('is_active', true)->whereIn('key', array_keys((array) config('catalog.categories')))->value('id') : null;
    }

    /** @return list<int> */
    private function fuzzyCatalogItemIds(?string $query): array
    {
        if ($query === null || trim($query) === '') {
            return [];
        }

        $needle = Str::of($query)->lower()->ascii()->squish()->toString();

        return DB::table('catalog_items')->join('catalog_categories', 'catalog_categories.id', '=', 'catalog_items.category_id')->where('catalog_items.is_active', true)->where('catalog_categories.is_active', true)->whereIn('catalog_categories.key', array_keys((array) config('catalog.categories')))->get(['catalog_items.id', 'canonical_name', 'aliases'])
            ->filter(function ($item) use ($needle): bool {
                foreach ([$item->canonical_name, ...((array) json_decode($item->aliases ?? '[]', true))] as $candidate) {
                    $normalized = Str::of((string) $candidate)->lower()->ascii()->squish()->toString();
                    if (str_contains($normalized, $needle) || levenshtein($normalized, $needle) <= max(2, (int) floor(strlen($needle) * .25))) {
                        return true;
                    }
                }

                return false;
            })->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }
}
