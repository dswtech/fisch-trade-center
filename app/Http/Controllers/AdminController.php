<?php
namespace App\Http\Controllers;

use App\Domain\Catalog\CatalogItemImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class AdminController extends Controller
{
    public function index(): Response
    {
        $imports = DB::table('value_imports')
            ->leftJoin('value_snapshots', 'value_snapshots.value_import_id', '=', 'value_imports.id')
            ->where('value_imports.source_key', 'admin-csv')
            ->orderByDesc('value_imports.id')->limit(20)
            ->get(['value_imports.public_id as id', 'value_imports.status', 'value_imports.checksum', 'value_imports.imported_at as importedAt', 'value_imports.validation_errors as errors', 'value_imports.payload', 'value_snapshots.id as publishedId'])
            ->map(function ($import): array {
                $payload = json_decode($import->payload ?? '{}', true) ?: [];
                return [
                    'id' => $import->id, 'status' => $import->status, 'checksum' => $import->checksum,
                    'importedAt' => $import->importedAt, 'errors' => json_decode($import->errors ?? '[]', true) ?: [],
                    'warnings' => $payload['warnings'] ?? [], 'preview' => $payload['preview'] ?? [],
                    'published' => $import->publishedId !== null,
                ];
            });
        $items = DB::table('catalog_items as ci')->join('catalog_categories as cc', 'cc.id', '=', 'ci.category_id')
            ->leftJoin('rods', 'rods.id', '=', 'ci.rod_id')
            ->whereIn('cc.key', array_keys((array) config('catalog.categories')))
            ->orderBy('cc.name')->orderBy('ci.canonical_name')
            ->get(['ci.public_id as id', 'ci.canonical_name as name', 'ci.aliases', 'ci.is_active as isActive', 'cc.key as categoryKey', 'cc.name as category', 'ci.thumbnail_path as thumbnailPath', 'rods.public_id as rodId', 'rods.name as rodName'])
            ->map(fn ($item): array => [
                'id' => $item->id, 'name' => $item->name, 'category' => $item->category, 'categoryKey' => $item->categoryKey,
                'aliases' => json_decode($item->aliases ?? '[]', true) ?: [], 'isActive' => (bool) $item->isActive,
                'rodId' => $item->rodId, 'rodName' => $item->rodName ?? 'Undefined Rod',
                'thumbnailUrl' => CatalogItemImage::url($item->thumbnailPath, $item->categoryKey), 'hasThumbnail' => $item->thumbnailPath !== null,
            ]);

        return Inertia::render('admin', [
            'imports' => $imports,
            'fairThreshold' => DB::table('system_settings')->where('key', 'fair_threshold_percent')->value('value'),
            'rods' => DB::table('rods')->orderByDesc('is_active')->orderBy('name')->get(['public_id as id', 'name', 'is_active as isActive']),
            'catalogItems' => $items,
        ]);
    }
    public function threshold(Request $request):RedirectResponse
    {
        $value=$request->validate(['fairThresholdPercent'=>['required','numeric','min:0','max:100']])['fairThresholdPercent'];
        DB::transaction(function()use($request,$value):void{DB::table('system_settings')->updateOrInsert(['key'=>'fair_threshold_percent'],['value'=>json_encode((float)$value),'updated_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);DB::table('audit_events')->insert(['public_id'=>(string)Str::uuid(),'actor_id'=>$request->user()->id,'event_type'=>'setting.fair_threshold.changed','subject_type'=>'system_setting','subject_public_id'=>'fair_threshold_percent','metadata'=>json_encode(['effective_for'=>'future_calculations','value'=>(float)$value]),'created_at'=>now()]);});
        return back()->with('success','Fair threshold updated prospectively.');
    }
}
