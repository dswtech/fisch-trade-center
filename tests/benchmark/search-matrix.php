<?php

use App\Domain\Trade\MarketInsightService;
use App\Models\Listing;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('benchmark') || DB::connection()->getDatabaseName() !== 'fisch_trade_benchmark') {
    throw new RuntimeException('Benchmark refuses to run outside the disposable benchmark database.');
}

$now = now();
$password = Hash::make('synthetic-benchmark-only');
foreach (array_chunk(range(1, 1000), 500) as $chunk) {
    DB::table('users')->insert(array_map(static fn (int $id): array => [
        'public_id' => sprintf('10000000-0000-4000-8000-%012d', $id),
        'name' => "Synthetic {$id}", 'email' => "synthetic-{$id}@example.test", 'password' => $password,
        'role' => 'player', 'theme' => 'dark', 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
    ], $chunk));
}

foreach (range(1, 3) as $id) {
    $categoryId = DB::table('catalog_categories')->insertGetId([
        'public_id' => sprintf('30000000-0000-4000-8000-%012d', $id), 'key' => "category-{$id}",
        'name' => "Category {$id}", 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('catalog_items')->insert([
        'public_id' => sprintf('40000000-0000-4000-8000-%012d', $id), 'category_id' => $categoryId,
        'canonical_name' => "Synthetic Item {$id}", 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
}

foreach (array_chunk(range(1, 100000), 1000) as $chunk) {
    DB::table('listings')->insert(array_map(static fn (int $id): array => [
        'public_id' => sprintf('20000000-0000-4000-8000-%012d', $id),
        'user_id' => (($id - 1) % 1000) + 1, 'status' => 'active', 'current_version' => 1,
        'published_at' => $now->copy()->subSeconds($id), 'expires_at' => $now->copy()->addDays(14),
        'created_at' => $now, 'updated_at' => $now,
    ], $chunk));
    DB::table('listing_versions')->insert(array_map(static fn (int $id): array => [
        'listing_id' => $id, 'version' => 1,
        'offered_items' => json_encode([['catalog_item_id' => ($id % 3) + 1, 'quantity' => 1]], JSON_THROW_ON_ERROR),
        'wanted_items' => json_encode([['catalog_item_id' => (($id + 1) % 3) + 1, 'quantity' => 1]], JSON_THROW_ON_ERROR),
        'offered_value' => 1000 + (($id % 50) * 100), 'wanted_value' => 1200 + (($id % 50) * 100),
        'created_at' => $now, 'updated_at' => $now,
    ], $chunk));
    $itemRows = [];
    foreach ($chunk as $id) {
        $itemRows[] = ['listing_version_id' => $id, 'catalog_item_id' => ($id % 3) + 1, 'side' => 'offered', 'quantity' => 1, 'unit_value' => 1000 + (($id % 50) * 100)];
        $itemRows[] = ['listing_version_id' => $id, 'catalog_item_id' => (($id + 1) % 3) + 1, 'side' => 'wanted', 'quantity' => 1, 'unit_value' => 1200 + (($id % 50) * 100)];
    }
    DB::table('listing_version_items')->insert($itemRows);
}

$scenarios = [
    'baseline' => static fn (): Builder => Listing::discoverable(1),
    'offered_item' => static fn (): Builder => Listing::discoverable(1)->withCurrentItem('offered', 1),
    'wanted_item' => static fn (): Builder => Listing::discoverable(1)->withCurrentItem('wanted', 2),
    'category' => static fn (): Builder => Listing::discoverable(1)->withCurrentCategory(1),
    'value_range' => static fn (): Builder => Listing::discoverable(1)->withCurrentOfferedValue(2400, 3200),
    'freshness' => static fn (): Builder => Listing::discoverable(1)->where('published_at', '>=', now()->subDay()),
    'combined' => static fn (): Builder => Listing::discoverable(1)->withCurrentItem('offered', 1)->withCurrentItem('wanted', 2)->withCurrentCategory(1)->withCurrentOfferedValue(1000, 5900)->where('published_at', '>=', now()->subWeek()),
];

$results = [];
foreach ($scenarios as $name => $query) {
    for ($warmup = 0; $warmup < 5; $warmup++) {
        $query()->with(['latestVersion.items.catalogItem'])->cursorPaginate(24);
    }
    $durations = [];
    for ($sample = 0; $sample < 50; $sample++) {
        $started = hrtime(true);
        $query()->with(['latestVersion.items.catalogItem'])->cursorPaginate(24);
        $durations[] = (hrtime(true) - $started) / 1_000_000;
    }
    sort($durations);
    $results[$name] = ['p50_ms' => round($durations[24], 2), 'p95_ms' => round($durations[47], 2), 'max_ms' => round(max($durations), 2)];
}

$marketInsights = app(MarketInsightService::class);
for ($warmup = 0; $warmup < 5; $warmup++) {
    $marketInsights->summary();
}
$marketDurations = [];
for ($sample = 0; $sample < 20; $sample++) {
    $started = hrtime(true);
    $marketInsights->summary();
    $marketDurations[] = (hrtime(true) - $started) / 1_000_000;
}
sort($marketDurations);
$marketResult = ['p50_ms' => round($marketDurations[9], 2), 'p95_ms' => round($marketDurations[18], 2), 'max_ms' => round(max($marketDurations), 2)];

$maximumP95 = max(max(array_column($results, 'p95_ms')), $marketResult['p95_ms']);
echo json_encode(['listings' => 100000, 'samples_per_scenario' => 50, 'market_samples' => 20, 'target_ms' => 1000, 'maximum_p95_ms' => $maximumP95, 'scenarios' => $results, 'market_insights' => $marketResult], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($maximumP95 <= 1000 ? 0 : 1);
