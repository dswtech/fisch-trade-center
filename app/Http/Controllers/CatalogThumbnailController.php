<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class CatalogThumbnailController extends Controller
{
    public function update(Request $request, string $item): RedirectResponse
    {
        $data = $request->validate(['thumbnail' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $catalogItem = CatalogItem::where('public_id', $item)->firstOrFail();
        $disk = (string) config('catalog.media_disk', 'public');
        $path = $data['thumbnail']->store('catalog-thumbnails', $disk);
        abort_unless($path, 500, 'Thumbnail could not be stored.');
        $old = $catalogItem->thumbnail_path;

        DB::transaction(function () use ($catalogItem, $path, $request): void {
            $catalogItem->update(['thumbnail_path' => $path]);
            DB::table('audit_events')->insert([
                'public_id' => (string) Str::uuid(), 'actor_id' => $request->user()->id,
                'event_type' => 'catalog.thumbnail.updated', 'subject_type' => 'catalog_item',
                'subject_public_id' => $catalogItem->public_id, 'metadata' => json_encode(['custom' => true]), 'created_at' => now(),
            ]);
        });
        if ($old) {
            Storage::disk($disk)->delete($old);
        }

        return back()->with('success', 'Thumbnail updated.');
    }

    public function destroy(Request $request, string $item): RedirectResponse
    {
        $catalogItem = CatalogItem::where('public_id', $item)->firstOrFail();
        $old = $catalogItem->thumbnail_path;
        DB::transaction(function () use ($catalogItem, $request): void {
            $catalogItem->update(['thumbnail_path' => null]);
            DB::table('audit_events')->insert([
                'public_id' => (string) Str::uuid(), 'actor_id' => $request->user()->id,
                'event_type' => 'catalog.thumbnail.removed', 'subject_type' => 'catalog_item',
                'subject_public_id' => $catalogItem->public_id, 'metadata' => json_encode(['fallback' => true]), 'created_at' => now(),
            ]);
        });
        if ($old) {
            Storage::disk((string) config('catalog.media_disk', 'public'))->delete($old);
        }

        return back()->with('success', 'Thumbnail removed; category fallback is active.');
    }
}
