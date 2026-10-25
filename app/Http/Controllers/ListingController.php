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
        abort_unless(DB::table('player_profiles')->where('user_id', $request->user()->id)->whereNotNull('usn')->whereNotNull('ign')->exists(), 422, 'Complete USN and IGN before publishing a listing.');
        $data=$this->validated($request); $offered=$this->offered($request,$data['offered']); $wanted=$this->wanted($data['wanted']);
        DB::transaction(function()use($request,$offered,$wanted):void{$snapshotId=$this->publishedSnapshotId();$listing=Listing::create(['user_id'=>$request->user()->id,'value_snapshot_id'=>$snapshotId,'status'=>'active','current_version'=>1,'published_at'=>now(),'expires_at'=>now()->addDays(14)]);$this->createVersion($listing,1,$offered,$wanted,$snapshotId);});
        return back()->with('success','Listing published for 14 days.');
    }
    public function update(Request $request,string $listing):RedirectResponse
    {
        $owned=Listing::where('public_id',$listing)->where('user_id',$request->user()->id)->firstOrFail();
        abort_unless($owned->status === 'active' && ($owned->expires_at === null || $owned->expires_at->isFuture()), 409, 'Only an active, unexpired listing can be edited.');
        $data=$this->validated($request); $offered=$this->offered($request,$data['offered']); $wanted=$this->wanted($data['wanted']);
        DB::transaction(function()use($owned,$offered,$wanted):void{$next=$owned->current_version+1;$snapshotId=$this->publishedSnapshotId();$owned->update(['current_version'=>$next,'value_snapshot_id'=>$snapshotId]);$this->createVersion($owned,$next,$offered,$wanted,$snapshotId);});
        return back()->with('success','A new listing version was created.');
    }
    public function destroy(Request $request,string $listing):RedirectResponse
    {
        $owned=Listing::where('public_id',$listing)->where('user_id',$request->user()->id)->firstOrFail();
        abort_unless($owned->status === 'active', 409, 'Only an active listing can be cancelled.');
        $owned->update(['status'=>'cancelled']); return back()->with('success','Listing cancelled.');
    }
    private function validated(Request $request):array{return $request->validate(['offered'=>['required','array','min:1'],'offered.*.inventoryEntryId'=>['required','uuid'],'offered.*.quantity'=>['required','integer','min:1'],'wanted'=>['required','array','min:1'],'wanted.*.catalogItemId'=>['required','uuid'],'wanted.*.quantity'=>['required','integer','min:1']]);}
    private function offered(Request $request,array $rows):array{return array_map(function(array $row)use($request){$entry=InventoryEntry::where('public_id',$row['inventoryEntryId'])->where('user_id',$request->user()->id)->whereNull('archived_at')->firstOrFail();abort_if($row['quantity']>$entry->quantity,422,'Offered quantity exceeds inventory.');return ['inventory_entry_id'=>$entry->public_id,'catalog_item_id'=>$entry->catalog_item_id,'quantity'=>$row['quantity']];},$rows);}
    private function wanted(array $rows):array{return array_map(function(array $row){$item=CatalogItem::where('public_id',$row['catalogItemId'])->where('is_active',true)->whereHas('category',fn($query)=>$query->where('is_active',true)->whereIn('key',array_keys((array)config('catalog.categories'))))->firstOrFail();return ['catalog_item_id'=>$item->id,'quantity'=>$row['quantity']];},$rows);}
    private function publishedSnapshotId():?int{return DB::table('value_snapshots')->whereNotNull('published_at')->orderByDesc('published_at')->orderByDesc('id')->value('id');}
    private function createVersion(Listing $listing,int $versionNumber,array $offered,array $wanted,?int $snapshotId):void
    {
        $catalogIds=collect([...$offered,...$wanted])->pluck('catalog_item_id')->unique()->values();
        $values=$snapshotId?DB::table('catalog_item_values')->where('snapshot_id',$snapshotId)->whereIn('catalog_item_id',$catalogIds)->pluck('unit_value','catalog_item_id'):collect();
        $total=static function(array $items)use($values):int|float|null{if($items===[]||collect($items)->contains(fn(array $item):bool=>!$values->has($item['catalog_item_id'])))return null;$value=collect($items)->sum(fn(array $item):float=>(float)$values[$item['catalog_item_id']]*(int)$item['quantity']);return floor($value)===$value?(int)$value:$value;};
        $version=ListingVersion::create(['listing_id'=>$listing->id,'version'=>$versionNumber,'value_snapshot_id'=>$snapshotId,'offered_items'=>$offered,'wanted_items'=>$wanted,'offered_value'=>$total($offered),'wanted_value'=>$total($wanted)]);
        $rows=collect(['offered'=>$offered,'wanted'=>$wanted])->flatMap(fn(array $items,string $side)=>collect($items)->map(fn(array $item):array=>['catalog_item_id'=>$item['catalog_item_id'],'side'=>$side,'quantity'=>$item['quantity'],'unit_value'=>$values->get($item['catalog_item_id'])]))->all();
        $version->items()->createMany($rows);
    }
}
