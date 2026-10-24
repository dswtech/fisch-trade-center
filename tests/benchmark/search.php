<?php

use App\Models\Listing;
use Illuminate\Contracts\Console\Kernel;
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

foreach (array_chunk(range(1, 100000), 1000) as $chunk) {
    DB::table('listings')->insert(array_map(static fn (int $id): array => [
        'public_id' => sprintf('20000000-0000-4000-8000-%012d', $id),
        'user_id' => (($id - 1) % 1000) + 1, 'status' => 'active', 'current_version' => 1,
        'published_at' => $now->copy()->subSeconds($id), 'expires_at' => $now->copy()->addDays(14),
        'created_at' => $now, 'updated_at' => $now,
    ], $chunk));
    DB::table('listing_versions')->insert(array_map(static fn (int $id): array => [
        'listing_id' => $id, 'version' => 1,
        'offered_items' => json_encode([['catalog_item_id' => 'synthetic-offer', 'quantity' => 1]], JSON_THROW_ON_ERROR),
        'wanted_items' => json_encode([['catalog_item_id' => 'synthetic-wanted', 'quantity' => 1]], JSON_THROW_ON_ERROR),
        'created_at' => $now, 'updated_at' => $now,
    ], $chunk));
}

for ($warmup = 0; $warmup < 10; $warmup++) {
    Listing::discoverable(1)->with('latestVersion')->cursorPaginate(24);
}

$durations = [];
for ($sample = 0; $sample < 100; $sample++) {
    $started = hrtime(true);
    Listing::discoverable(1)->with('latestVersion')->cursorPaginate(24);
    $durations[] = (hrtime(true) - $started) / 1_000_000;
}
sort($durations);
$result = [
    'listings' => 100000,
    'samples' => count($durations),
    'p50_ms' => round($durations[49], 2),
    'p95_ms' => round($durations[94], 2),
    'max_ms' => round(max($durations), 2),
    'target_ms' => 1000,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($result['p95_ms'] <= $result['target_ms'] ? 0 : 1);
