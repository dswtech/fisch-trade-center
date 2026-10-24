<?php
namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\InventoryEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function store(Request $request):RedirectResponse
    {
        $data=$request->validate(['catalogItemId'=>['required','uuid'],'quantity'=>['required','integer','min:1','max:999']]);
        $item=CatalogItem::where('public_id',$data['catalogItemId'])->where('is_active',true)->firstOrFail();
        InventoryEntry::updateOrCreate(['user_id'=>$request->user()->id,'catalog_item_id'=>$item->id],['quantity'=>$data['quantity'],'archived_at'=>null]);
        return back()->with('success','Inventory updated.');
    }
    public function update(Request $request,string $entry):RedirectResponse
    {
        $owned=InventoryEntry::where('public_id',$entry)->where('user_id',$request->user()->id)->firstOrFail();
        $owned->update($request->validate(['quantity'=>['required','integer','min:1','max:999']])); return back()->with('success','Quantity updated.');
    }
    public function destroy(Request $request,string $entry):RedirectResponse
    {
        InventoryEntry::where('public_id',$entry)->where('user_id',$request->user()->id)->firstOrFail()->update(['archived_at'=>now()]); return back()->with('success','Inventory entry archived.');
    }
}
