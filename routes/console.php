<?php

use App\Domain\Trade\PhaseTwoExpiryService;
use App\Domain\Trade\PhaseFourExpiryService;
use App\Domain\Operations\RetentionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn (): array => app(PhaseTwoExpiryService::class)->expire())
    ->name('trade:expire-phase-three')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::call(fn (): array => app(PhaseFourExpiryService::class)->expire())
    ->name('trade:expire-phase-four')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('ops:heartbeat')
    ->name('operations:heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('ops:retention --execute')
    ->dailyAt('02:30')
    ->when(fn (): bool => (bool) config('operations.retention_enabled'))
    ->withoutOverlapping();

Artisan::command('ops:heartbeat', function (): int {
    Cache::forever((string) config('operations.heartbeat_key'), now()->getTimestamp());

    return Command::SUCCESS;
})->purpose('Record scheduler readiness heartbeat.');

Artisan::command('ops:retention {--execute}', function (RetentionService $retention): int {
    $rows = collect($retention->plan())->map(fn (int $count, string $type): array => [$type, $count])->values()->all();
    $this->table(['candidate', 'count'], $rows);
    if (! $this->option('execute')) {
        $this->info('Dry run only. Pass --execute after review.');

        return Command::SUCCESS;
    }
    if (! config('operations.retention_enabled')) {
        $this->error('RETENTION_ENABLED must be true before execution.');

        return Command::FAILURE;
    }
    $deleted = $retention->execute();
    $this->info('Retention completed: '.json_encode($deleted, JSON_THROW_ON_ERROR));

    return Command::SUCCESS;
})->purpose('Preview or execute approved data retention policy.');

Artisan::command('catalog:remove-fishing-rods {--confirm}', function (): int {
    if (! $this->option('confirm')) {
        $this->error('Pass --confirm to run the approved one-time cleanup.');
        return 1;
    }

    $itemIds = DB::table('catalog_items as ci')->join('catalog_categories as cc', 'cc.id', '=', 'ci.category_id')->where('cc.key', 'fishing_rod')->pluck('ci.id');
    if ($itemIds->isEmpty()) {
        $this->info('Fishing Rod catalog data is already absent.');
        return 0;
    }
    $listingIds = DB::table('listing_version_items as lvi')->join('listing_versions as lv', 'lv.id', '=', 'lvi.listing_version_id')->whereIn('lvi.catalog_item_id', $itemIds)->distinct()->pluck('lv.listing_id');
    $counts = [
        'items' => $itemIds->count(),
        'inventory' => DB::table('inventory_entries')->whereIn('catalog_item_id', $itemIds)->count(),
        'values' => DB::table('catalog_item_values')->whereIn('catalog_item_id', $itemIds)->count(),
        'listing_refs' => DB::table('listing_version_items')->whereIn('catalog_item_id', $itemIds)->count(),
        'listings' => $listingIds->count(),
    ];
    if ($counts !== ['items' => 1, 'inventory' => 0, 'values' => 1, 'listing_refs' => 26, 'listings' => 26]) {
        $this->error('Cleanup aborted because Fishing Rod counts no longer match the approved synthetic dataset: '.json_encode($counts));
        return 1;
    }

    DB::transaction(function () use ($itemIds, $listingIds): void {
        DB::table('listings')->whereIn('id', $listingIds)->delete();
        $fixtureImportIds = DB::table('value_imports')->where('source_key', 'e2e-fixture')->pluck('id');
        $fixtureSnapshotIds = DB::table('value_snapshots')->whereIn('value_import_id', $fixtureImportIds)->pluck('id');
        DB::table('catalog_item_values')->whereIn('snapshot_id', $fixtureSnapshotIds)->delete();
        DB::table('value_snapshots')->whereIn('id', $fixtureSnapshotIds)->delete();
        DB::table('value_imports')->whereIn('id', $fixtureImportIds)->delete();
        DB::table('catalog_items')->whereIn('id', $itemIds)->delete();
        DB::table('catalog_categories')->where('key', 'fishing_rod')->delete();
    });
    $this->info('Removed the approved synthetic Fishing Rod dataset.');
    return 0;
})->purpose('Remove the exact approved synthetic Fishing Rod dataset');
