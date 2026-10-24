<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PhaseOnePageController extends Controller
{
    public function catalog():Response{return Inertia::render('workspace',['section'=>'catalog','items'=>DB::table('catalog_items')->join('catalog_categories','catalog_categories.id','=','catalog_items.category_id')->where('catalog_items.is_active',true)->orderBy('canonical_name')->get(['catalog_items.public_id as id','canonical_name as name','catalog_categories.name as category'])]);}
    public function inventory(Request $request):Response{return Inertia::render('workspace',['section'=>'inventory','items'=>$this->catalogItems(),'entries'=>DB::table('inventory_entries')->join('catalog_items','catalog_items.id','=','inventory_entries.catalog_item_id')->where('user_id',$request->user()->id)->whereNull('archived_at')->orderBy('canonical_name')->get(['inventory_entries.public_id as id','catalog_items.public_id as catalogItemId','canonical_name as name','quantity'])]);}
    public function listings(Request $request):Response{return Inertia::render('workspace',['section'=>'listings','listings'=>DB::table('listings')->where('user_id',$request->user()->id)->orderByDesc('id')->get(['public_id as id','status','current_version as version','expires_at as expiresAt'])]);}
    public function listingCreate(Request $request):Response{return Inertia::render('workspace',['section'=>'listing-create','items'=>$this->catalogItems(),'entries'=>DB::table('inventory_entries')->join('catalog_items','catalog_items.id','=','inventory_entries.catalog_item_id')->where('user_id',$request->user()->id)->whereNull('archived_at')->get(['inventory_entries.public_id as id','canonical_name as name','quantity'])]);}
    public function simulator():Response{return Inertia::render('workspace',['section'=>'simulator','items'=>$this->catalogItems()]);}
    public function profile(Request $request):Response{return Inertia::render('workspace',['section'=>'profile','profile'=>DB::table('player_profiles')->where('user_id',$request->user()->id)->first(['usn','ign'])]);}
    private function catalogItems(){return DB::table('catalog_items')->where('is_active',true)->orderBy('canonical_name')->get(['public_id as id','canonical_name as name']);}
}
