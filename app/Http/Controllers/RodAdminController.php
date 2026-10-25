<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditRecorder;
use App\Models\CatalogItem;
use App\Models\Rod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class RodAdminController extends Controller
{
    public function store(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'not_regex:/^[=+\-@]/', 'unique:rods,name']]);
        DB::transaction(function () use ($data, $request, $audit): void {
            $rod = Rod::create(['name' => trim($data['name']), 'is_active' => true]);
            $audit->record($request->user(), 'rod.created', 'rod', $rod->public_id, after: ['name' => $rod->name, 'is_active' => true], request: $request);
        });

        return back()->with('success', 'Rod master created.');
    }

    public function update(Request $request, string $rod, AuditRecorder $audit): RedirectResponse
    {
        $record = Rod::query()->where('public_id', $rod)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'not_regex:/^[=+\-@]/', Rule::unique('rods', 'name')->ignore($record->id)],
            'isActive' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($record, $data, $request, $audit): void {
            $before = ['name' => $record->name, 'is_active' => $record->is_active];
            $record->forceFill(['name' => trim($data['name']), 'is_active' => $data['isActive']])->save();
            $audit->record($request->user(), 'rod.updated', 'rod', $record->public_id, $before, ['name' => $record->name, 'is_active' => $record->is_active], request: $request);
        });

        return back()->with('success', 'Rod master updated.');
    }

    public function assign(Request $request, string $item, AuditRecorder $audit): RedirectResponse
    {
        $catalogItem = CatalogItem::query()->where('public_id', $item)->firstOrFail();
        abort_unless($catalogItem->category()->where('key', 'rod_skin')->exists(), 422, 'Only Rod Skin can reference a Rod.');
        $data = $request->validate(['rodId' => ['nullable', 'uuid', Rule::exists('rods', 'public_id')->where('is_active', true)]]);
        $rodId = isset($data['rodId']) ? Rod::where('public_id', $data['rodId'])->value('id') : null;
        DB::transaction(function () use ($catalogItem, $rodId, $request, $audit): void {
            $before = ['rod_id' => $catalogItem->rod_id];
            $catalogItem->update(['rod_id' => $rodId]);
            $audit->record($request->user(), 'catalog_item.rod_assigned', 'catalog_item', $catalogItem->public_id, $before, ['rod_id' => $rodId], request: $request);
        });

        return back()->with('success', 'Rod assignment updated.');
    }
}
