<?php
namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\InventoryEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function store(Request $request):RedirectResponse
    {
        $data=$request->validate(['catalogItemId'=>['required','uuid'],'quantity'=>['required','integer','min:1','max:999']]);
        $item=CatalogItem::where('public_id',$data['catalogItemId'])->where('is_active',true)->firstOrFail();
        DB::transaction(function () use ($request, $item, $data): void {
            $entry=InventoryEntry::firstOrNew(['user_id'=>$request->user()->id,'catalog_item_id'=>$item->id]);
            $entry->fill(['quantity'=>$data['quantity'],'archived_at'=>null])->save();
            $this->invalidateListings($entry, (int) $data['quantity']);
        });
        return back()->with('success','Inventory updated.');
    }
    public function update(Request $request,string $entry):RedirectResponse
    {
        $owned=InventoryEntry::where('public_id',$entry)->where('user_id',$request->user()->id)->firstOrFail();
        $quantity=(int)$request->validate(['quantity'=>['required','integer','min:1','max:999']])['quantity'];
        DB::transaction(function () use ($owned, $quantity): void {
            $owned->update(['quantity'=>$quantity]);
            $this->invalidateListings($owned, $quantity);
        });
        return back()->with('success','Quantity updated.');
    }
    public function destroy(Request $request,string $entry):RedirectResponse
    {
        $owned=InventoryEntry::where('public_id',$entry)->where('user_id',$request->user()->id)->firstOrFail();
        DB::transaction(function () use ($owned): void {
            $owned->update(['archived_at'=>now()]);
            $this->invalidateListings($owned, 0);
        });
        return back()->with('success','Inventory entry archived.');
    }

    private function invalidateListings(InventoryEntry $entry, int $availableQuantity): void
    {
        Listing::where('user_id', $entry->user_id)->where('status', 'active')->with('latestVersion')->get()
            ->filter(function (Listing $listing) use ($entry, $availableQuantity): bool {
                return collect($listing->latestVersion?->offered_items ?? [])->contains(
                    fn (array $item): bool => ($item['inventory_entry_id'] ?? null) === $entry->public_id && (int) $item['quantity'] > $availableQuantity,
                );
            })->each->update(['status' => 'invalidated']);
    }
}
