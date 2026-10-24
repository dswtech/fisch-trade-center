<?php
namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request):Response
    {
        $request->validate(['freshness'=>['nullable','in:hour,day,week'],'cursor'=>['nullable','string','max:500']]);
        $query=Listing::discoverable($request->user()?->id)->when($request->freshness==='hour',fn($q)=>$q->where('published_at','>=',now()->subHour()))->when($request->freshness==='day',fn($q)=>$q->where('published_at','>=',now()->subDay()))->with(['latestVersion']);
        $page=$query->cursorPaginate(24)->withQueryString();
        $listings=collect($page->items())->map(fn(Listing $listing)=>['id'=>$listing->public_id,'alias'=>'Angler '.str_pad((string)(crc32($listing->public_id)%100),2,'0',STR_PAD_LEFT),'offered'=>count($listing->latestVersion?->offered_items??[]).' item(s)','wanted'=>count($listing->latestVersion?->wanted_items??[]).' item(s)','value'=>0,'age'=>$listing->published_at?->diffForHumans(short:true),'status'=>'unavailable']);
        return Inertia::render('dashboard',['listings'=>$listings,'nextCursor'=>$page->nextCursor()?->encode()]);
    }
}
