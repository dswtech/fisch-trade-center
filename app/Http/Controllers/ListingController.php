<?php
namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\InventoryEntry;
use App\Models\Listing;
use App\Models\ListingVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListingController extends Controller
{
    public function store(Request $request):RedirectResponse
    {
        $data=$this->validated($request); $offered=$this->offered($request,$data['offered']); $wanted=$this->wanted($data['wanted']);
        DB::transaction(function()use($request,$offered,$wanted):void{$listing=Listing::create(['user_id'=>$request->user()->id,'status'=>'active','current_version'=>1,'published_at'=>now(),'expires_at'=>now()->addDays(14)]);ListingVersion::create(['listing_id'=>$listing->id,'version'=>1,'offered_items'=>$offered,'wanted_items'=>$wanted]);});
        return back()->with('success','Listing published for 14 days.');
    }
    public function update(Request $request,string $listing):RedirectResponse
    {
        $owned=Listing::where('public_id',$listing)->where('user_id',$request->user()->id)->firstOrFail(); $data=$this->validated($request); $offered=$this->offered($request,$data['offered']); $wanted=$this->wanted($data['wanted']);
        DB::transaction(function()use($owned,$offered,$wanted):void{$next=$owned->current_version+1;$owned->update(['current_version'=>$next]);ListingVersion::create(['listing_id'=>$owned->id,'version'=>$next,'offered_items'=>$offered,'wanted_items'=>$wanted]);});
        return back()->with('success','A new listing version was created.');
    }
    public function destroy(Request $request,string $listing):RedirectResponse
    {
        Listing::where('public_id',$listing)->where('user_id',$request->user()->id)->firstOrFail()->update(['status'=>'cancelled']); return back()->with('success','Listing cancelled.');
    }
    private function validated(Request $request):array{return $request->validate(['offered'=>['required','array','min:1'],'offered.*.inventoryEntryId'=>['required','uuid'],'offered.*.quantity'=>['required','integer','min:1'],'wanted'=>['required','array','min:1'],'wanted.*.catalogItemId'=>['required','uuid'],'wanted.*.quantity'=>['required','integer','min:1']]);}
    private function offered(Request $request,array $rows):array{return array_map(function(array $row)use($request){$entry=InventoryEntry::where('public_id',$row['inventoryEntryId'])->where('user_id',$request->user()->id)->whereNull('archived_at')->firstOrFail();abort_if($row['quantity']>$entry->quantity,422,'Offered quantity exceeds inventory.');return ['inventory_entry_id'=>$entry->public_id,'catalog_item_id'=>$entry->catalog_item_id,'quantity'=>$row['quantity']];},$rows);}
    private function wanted(array $rows):array{return array_map(function(array $row){$item=CatalogItem::where('public_id',$row['catalogItemId'])->where('is_active',true)->firstOrFail();return ['catalog_item_id'=>$item->public_id,'quantity'=>$row['quantity']];},$rows);}
}
