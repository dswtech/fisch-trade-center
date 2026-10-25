<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Rod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RodAdminController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'not_regex:/^[=+\-@]/', 'unique:rods,name']]);
        Rod::create(['name' => trim($data['name'])]);

        return back()->with('success', 'Rod master created.');
    }

    public function assign(Request $request, string $item): RedirectResponse
    {
        $catalogItem = CatalogItem::query()->where('public_id', $item)->firstOrFail();
        abort_unless($catalogItem->category()->where('key', 'rod_skin')->exists(), 422, 'Only Rod Skin can reference a Rod.');
        $data = $request->validate(['rodId' => ['nullable', 'uuid', Rule::exists('rods', 'public_id')]]);
        $rodId = isset($data['rodId']) ? Rod::where('public_id', $data['rodId'])->value('id') : null;
        $catalogItem->update(['rod_id' => $rodId]);

        return back()->with('success', 'Rod assignment updated.');
    }
}
