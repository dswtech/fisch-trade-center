<?php
namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function update(Request $request):RedirectResponse
    {
        $data=$request->validate(['usn'=>['required','string','max:50'],'ign'=>['required','string','max:50']]);
        DB::transaction(function()use($request,$data):void{DB::table('player_profiles')->updateOrInsert(['user_id'=>$request->user()->id],[...$data,'created_at'=>now(),'updated_at'=>now()]);});
        return back()->with('success','Profile saved. USN and IGN remain unverified.');
    }
}
