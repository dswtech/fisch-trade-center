<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\CatalogAdminService;
use App\Models\CatalogItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CatalogAdminController extends Controller
{
    public function update(Request $request, string $item, CatalogAdminService $service): RedirectResponse
    {
        $catalogItem = CatalogItem::query()->where('public_id', $item)->firstOrFail();
        $data = $request->validate([
            'isActive' => ['required', 'boolean'],
            'aliases' => ['present', 'array', 'max:50'],
            'aliases.*' => ['string', 'max:120', 'distinct:ignore_case'],
        ]);
        $service->update($catalogItem, $request->user(), $data['isActive'], $data['aliases'], $request);

        return back()->with('success', 'Catalog item updated without changing its historical identity.');
    }
}
