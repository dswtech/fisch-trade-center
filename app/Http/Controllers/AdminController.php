<?php
namespace App\Http\Controllers;

use App\Domain\Value\FixtureValueSource;
use App\Domain\Value\ValueImportValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index():Response{return Inertia::render('admin',['imports'=>DB::table('value_imports')->orderByDesc('id')->limit(20)->get(['public_id as id','source_key as source','status','checksum','imported_at as importedAt']),'fairThreshold'=>DB::table('system_settings')->where('key','fair_threshold_percent')->value('value')]);}
    public function threshold(Request $request):RedirectResponse
    {
        $value=$request->validate(['fairThresholdPercent'=>['required','numeric','min:0','max:100']])['fairThresholdPercent'];
        DB::transaction(function()use($request,$value):void{DB::table('system_settings')->updateOrInsert(['key'=>'fair_threshold_percent'],['value'=>json_encode((float)$value),'updated_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);DB::table('audit_events')->insert(['public_id'=>(string)Str::uuid(),'actor_id'=>$request->user()->id,'event_type'=>'setting.fair_threshold.changed','subject_type'=>'system_setting','subject_public_id'=>'fair_threshold_percent','metadata'=>json_encode(['effective_for'=>'future_calculations','value'=>(float)$value]),'created_at'=>now()]);});
        return back()->with('success','Fair threshold updated prospectively.');
    }
    public function importFixture(Request $request,ValueImportValidator $validator):RedirectResponse
    {
        $payload=(new FixtureValueSource(database_path('fixtures/value-snapshot-v1.json')))->fetch();$result=$validator->validate($payload['rows']);
        DB::table('value_imports')->insert(['public_id'=>(string)Str::uuid(),'source_key'=>'fixture-v1','status'=>$result['valid']?'valid':'invalid','checksum'=>$result['checksum'],'source_timestamp'=>$payload['source_timestamp'],'validation_errors'=>json_encode($result['errors']),'imported_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return back()->with($result['valid']?'success':'error',$result['valid']?'Fixture import validated.':'Fixture import rejected; published snapshot unchanged.');
    }
    public function publish(Request $request):RedirectResponse
    {
        $data=$request->validate(['importId'=>['nullable','uuid']]);
        $import=DB::table('value_imports')->where('status','valid')->when($data['importId']??null,fn($query,$id)=>$query->where('public_id',$id))->orderByDesc('id')->first();
        abort_unless($import,422,'No valid import is available to publish.');
        if(DB::table('value_snapshots')->where('checksum',$import->checksum)->exists())return back()->with('success','Snapshot already published; checksum is idempotent.');
        DB::transaction(function()use($request,$import):void{DB::table('value_snapshots')->insert(['public_id'=>(string)Str::uuid(),'value_import_id'=>$import->id,'version'=>'snapshot-'.now()->format('YmdHis'),'checksum'=>$import->checksum,'source_timestamp'=>$import->source_timestamp,'published_at'=>now(),'published_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);DB::table('audit_events')->insert(['public_id'=>(string)Str::uuid(),'actor_id'=>$request->user()->id,'event_type'=>'value_snapshot.published','subject_type'=>'value_import','subject_public_id'=>$import->public_id,'metadata'=>json_encode(['checksum'=>$import->checksum]),'created_at'=>now()]);});
        return back()->with('success','Immutable value snapshot published.');
    }
}
