<?php
namespace App\Http\Controllers;

use App\Domain\Value\FixtureValueSource;
use App\Domain\Value\ValueImportService;
use App\Domain\Value\ValueImportValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index():Response
    {
        return Inertia::render('admin', [
            'imports' => DB::table('value_imports')
                ->leftJoin('value_snapshots', 'value_snapshots.value_import_id', '=', 'value_imports.id')
                ->orderByDesc('value_imports.id')->limit(20)
                ->get([
                    'value_imports.public_id as id', 'value_imports.source_key as source', 'value_imports.status',
                    'value_imports.checksum', 'value_imports.imported_at as importedAt',
                    'value_imports.validation_errors as errors', 'value_snapshots.id as publishedId',
                ])->map(fn ($import): array => [
                    'id' => $import->id,
                    'source' => $import->source,
                    'status' => $import->status,
                    'checksum' => $import->checksum,
                    'importedAt' => $import->importedAt,
                    'errors' => json_decode($import->errors ?? '[]', true),
                    'published' => $import->publishedId !== null,
                ]),
            'fairThreshold' => DB::table('system_settings')->where('key', 'fair_threshold_percent')->value('value'),
            'valueProvider' => [
                'enabled' => (bool) config('value_source.enabled'),
                'schedule' => config('value_source.schedule').' '.config('value_source.timezone'),
            ],
        ]);
    }
    public function threshold(Request $request):RedirectResponse
    {
        $value=$request->validate(['fairThresholdPercent'=>['required','numeric','min:0','max:100']])['fairThresholdPercent'];
        DB::transaction(function()use($request,$value):void{DB::table('system_settings')->updateOrInsert(['key'=>'fair_threshold_percent'],['value'=>json_encode((float)$value),'updated_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);DB::table('audit_events')->insert(['public_id'=>(string)Str::uuid(),'actor_id'=>$request->user()->id,'event_type'=>'setting.fair_threshold.changed','subject_type'=>'system_setting','subject_public_id'=>'fair_threshold_percent','metadata'=>json_encode(['effective_for'=>'future_calculations','value'=>(float)$value]),'created_at'=>now()]);});
        return back()->with('success','Fair threshold updated prospectively.');
    }
    public function importFixture(Request $request,ValueImportValidator $validator):RedirectResponse
    {
        $payload=(new FixtureValueSource(database_path('fixtures/value-snapshot-v1.json')))->fetch();$result=$validator->validate($payload['rows']);
        DB::table('value_imports')->insert(['public_id'=>(string)Str::uuid(),'source_key'=>'fixture-v1','status'=>$result['valid']?'valid':'invalid','checksum'=>$result['checksum'],'source_timestamp'=>$payload['source_timestamp'],'validation_errors'=>json_encode($result['errors']),'payload'=>json_encode($payload['rows'],JSON_THROW_ON_ERROR),'imported_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return back()->with($result['valid']?'success':'error',$result['valid']?'Fixture import validated.':'Fixture import rejected; published snapshot unchanged.');
    }

    public function importProduction(ValueImportService $service): RedirectResponse
    {
        abort_unless(config('value_source.enabled'), 422, 'Production value source is disabled.');
        $result = $service->import();

        return back()->with(
            $result['status'] === 'valid' ? 'success' : 'error',
            $result['status'] === 'valid'
                ? 'Production import staged and ready for review.'
                : 'Production import was rejected; the published snapshot is unchanged.'
        );
    }
    public function publish(Request $request):RedirectResponse
    {
        $data=$request->validate(['importId'=>['nullable','uuid']]);
        $import=DB::table('value_imports')->where('status','valid')->when($data['importId']??null,fn($query,$id)=>$query->where('public_id',$id))->orderByDesc('id')->first();
        abort_unless($import,422,'No valid import is available to publish.');
        if(DB::table('value_snapshots')->where('checksum',$import->checksum)->exists())return back()->with('success','Snapshot already published; checksum is idempotent.');
        DB::transaction(function()use($request,$import):void{
            $rows=json_decode($import->payload??'[]',true,flags:JSON_THROW_ON_ERROR);
            $mapped=collect($rows)->map(function(array $row):array{
                $itemId=isset($row['catalog_item_public_id'])
                    ? DB::table('catalog_items')->where('public_id',$row['catalog_item_public_id'])->where('is_active',true)->value('id')
                    : DB::table('catalog_items')->join('catalog_categories','catalog_categories.id','=','catalog_items.category_id')->where('catalog_items.canonical_name',$row['name'])->where('catalog_categories.key',$row['category'])->value('catalog_items.id');
                abort_unless($itemId,422,'Import contains an unmapped catalog item.');
                return ['catalog_item_id'=>$itemId,'unit_value'=>$row['value'],'offer_range'=>$row['offer_range']??null];
            });
            abort_if($mapped->isEmpty(),422,'Import contains no publishable rows.');
            $snapshotId=DB::table('value_snapshots')->insertGetId(['public_id'=>(string)Str::uuid(),'value_import_id'=>$import->id,'version'=>'snapshot-'.now()->format('YmdHis').'-'.substr($import->checksum,0,8),'checksum'=>$import->checksum,'source_timestamp'=>$import->source_timestamp,'published_at'=>now(),'published_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('catalog_item_values')->insert($mapped->map(fn(array $row):array=>['snapshot_id'=>$snapshotId,...$row])->all());
            DB::table('audit_events')->insert(['public_id'=>(string)Str::uuid(),'actor_id'=>$request->user()->id,'event_type'=>'value_snapshot.published','subject_type'=>'value_import','subject_public_id'=>$import->public_id,'metadata'=>json_encode(['checksum'=>$import->checksum]),'created_at'=>now()]);
        });
        return back()->with('success','Immutable value snapshot published.');
    }
}
