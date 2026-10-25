<?php

use Illuminate\Foundation\Inspiring;
use App\Domain\Value\ValueImportService;
use App\Domain\Catalog\CuratedSkinCatalogImporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('values:import', function (ValueImportService $service): int {
    if (! config('value_source.enabled')) {
        $this->error('Production value source is disabled.');
        return 1;
    }
    $result = $service->import();
    if ($result['status'] === 'valid') {
        $this->info("Value import {$result['public_id']} finished with status valid.");
    } else {
        $this->error("Value import {$result['public_id']} finished with status invalid.");
    }

    return $result['status'] === 'valid' ? 0 : 1;
})->purpose('Fetch and validate the configured production value source into staging');

Artisan::command('catalog:import-skins', function (CuratedSkinCatalogImporter $importer): int {
    try {
        $result = $importer->import();
    } catch (\DomainException $exception) {
        $this->error($exception->getMessage());
        return 1;
    }

    $this->info("Curated skin catalog imported: {$result['created']} created, {$result['matched']} matched, {$result['total']} total.");
    return 0;
})->purpose('Import the reviewed skin catalog manifest without fetching external data');

Schedule::command('values:import')
    ->dailyAt((string) config('value_source.schedule', '02:00'))
    ->timezone((string) config('value_source.timezone', 'Asia/Jakarta'))
    ->withoutOverlapping();
