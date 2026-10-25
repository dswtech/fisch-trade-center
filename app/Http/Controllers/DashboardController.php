<?php
namespace App\Http\Controllers;

use App\Domain\Trade\DiscoveryPresenter;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DiscoveryPresenter $presenter): Response
    {
        $listings = Listing::discoverable(request()->user()?->id)
            ->with(['latestVersion.items.catalogItem.category'])
            ->limit(4)
            ->get()
            ->map($presenter->listing(...));

        return Inertia::render('discovery', [
            'synthetic' => false,
            'snapshot' => $presenter->activeSnapshot(),
            'listings' => $listings,
            'filterOptions' => [
                'items' => DB::table('catalog_items')->join('catalog_categories', 'catalog_categories.id', '=', 'catalog_items.category_id')->where('catalog_items.is_active', true)->where('catalog_categories.is_active', true)->whereIn('catalog_categories.key', array_keys((array) config('catalog.categories')))->orderBy('canonical_name')->get(['catalog_items.public_id as id', 'canonical_name as name']),
                'categories' => DB::table('catalog_categories')->where('is_active', true)->whereIn('key', array_keys((array) config('catalog.categories')))->orderBy('name')->get(['public_id as id', 'name']),
            ],
        ]);
    }
}
