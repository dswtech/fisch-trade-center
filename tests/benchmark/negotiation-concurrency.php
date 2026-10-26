<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('benchmark') || DB::connection()->getDatabaseName() !== 'fisch_trade_benchmark') {
    throw new RuntimeException('Concurrency test refuses to run outside the disposable benchmark database.');
}

$now = now();
$initiator = User::create(['name' => 'Race Initiator', 'email' => 'race-initiator@example.test', 'password' => 'synthetic-only', 'role' => 'player', 'email_verified_at' => $now]);
$owner = User::create(['name' => 'Race Owner', 'email' => 'race-owner@example.test', 'password' => 'synthetic-only', 'role' => 'player', 'email_verified_at' => $now]);
$categoryId = DB::table('catalog_categories')->insertGetId(['public_id' => (string) Str::uuid(), 'key' => 'boat', 'name' => 'Boats', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
$itemIds = [
    DB::table('catalog_items')->insertGetId(['public_id' => (string) Str::uuid(), 'category_id' => $categoryId, 'canonical_name' => 'Race Boat A', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]),
    DB::table('catalog_items')->insertGetId(['public_id' => (string) Str::uuid(), 'category_id' => $categoryId, 'canonical_name' => 'Race Boat B', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]),
];
$importId = DB::table('value_imports')->insertGetId([
    'public_id' => (string) Str::uuid(), 'source_key' => 'negotiation-race', 'status' => 'published', 'checksum' => hash('sha256', 'negotiation-race'),
    'source_timestamp' => $now, 'validation_errors' => json_encode([]), 'payload' => json_encode([]), 'imported_at' => $now, 'created_at' => $now, 'updated_at' => $now,
]);
$snapshotId = DB::table('value_snapshots')->insertGetId([
    'public_id' => (string) Str::uuid(), 'value_import_id' => $importId, 'version' => 'negotiation-race', 'checksum' => hash('sha256', 'negotiation-race-snapshot'),
    'source_timestamp' => $now, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now,
]);
foreach ($itemIds as $itemId) {
    DB::table('catalog_item_values')->insert(['snapshot_id' => $snapshotId, 'catalog_item_id' => $itemId, 'unit_value' => 100]);
}
$entries = [
    DB::table('inventory_entries')->insertGetId(['public_id' => (string) Str::uuid(), 'user_id' => $initiator->id, 'catalog_item_id' => $itemIds[0], 'quantity' => 1, 'created_at' => $now, 'updated_at' => $now]),
    DB::table('inventory_entries')->insertGetId(['public_id' => (string) Str::uuid(), 'user_id' => $owner->id, 'catalog_item_id' => $itemIds[1], 'quantity' => 1, 'created_at' => $now, 'updated_at' => $now]),
];
$listingId = DB::table('listings')->insertGetId([
    'public_id' => (string) Str::uuid(), 'user_id' => $owner->id, 'value_snapshot_id' => $snapshotId, 'status' => 'active', 'current_version' => 1,
    'published_at' => $now, 'expires_at' => $now->copy()->addDays(14), 'created_at' => $now, 'updated_at' => $now,
]);
$negotiationId = DB::table('negotiations')->insertGetId([
    'public_id' => (string) Str::uuid(), 'listing_id' => $listingId, 'initiator_id' => $initiator->id, 'owner_id' => $owner->id,
    'value_snapshot_id' => $snapshotId, 'status' => 'proposed', 'active_proposal_version' => 1, 'initiator_alias' => 'Trader-RACEA',
    'owner_alias' => 'Trader-RACEB', 'last_activity_at' => $now, 'expires_at' => $now->copy()->addDays(7), 'created_at' => $now, 'updated_at' => $now,
]);
$proposalId = DB::table('proposal_versions')->insertGetId([
    'public_id' => (string) Str::uuid(), 'negotiation_id' => $negotiationId, 'proposer_id' => $initiator->id, 'value_snapshot_id' => $snapshotId,
    'version' => 1, 'status' => 'active', 'initiator_value' => 100, 'owner_value' => 100, 'expires_at' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now,
]);
DB::table('proposal_items')->insert([
    ['proposal_version_id' => $proposalId, 'owner_user_id' => $initiator->id, 'inventory_entry_id' => $entries[0], 'catalog_item_id' => $itemIds[0], 'side' => 'initiator', 'quantity' => 1, 'unit_value' => 100],
    ['proposal_version_id' => $proposalId, 'owner_user_id' => $owner->id, 'inventory_entry_id' => $entries[1], 'catalog_item_id' => $itemIds[1], 'side' => 'owner', 'quantity' => 1, 'unit_value' => 100],
]);

DB::unprepared('CREATE FUNCTION benchmark_pause_acceptance() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN PERFORM pg_sleep(0.35); RETURN NEW; END; $$');
DB::unprepared('CREATE TRIGGER benchmark_pause_acceptance BEFORE INSERT ON proposal_acceptances FOR EACH ROW EXECUTE FUNCTION benchmark_pause_acceptance()');
DB::disconnect();

$syncPath = __DIR__.'/.concurrency';
if (! is_dir($syncPath) && ! mkdir($syncPath)) {
    throw new RuntimeException('Could not create concurrency sync directory.');
}
$goPath = $syncPath.'/go';
$workers = [];
foreach ([$initiator->id, $owner->id] as $index => $userId) {
    $readyPath = $syncPath.'/ready-'.$index;
    $pipes = [];
    $process = proc_open([PHP_BINARY, __DIR__.'/negotiation-accept-worker.php', (string) $userId, (string) $negotiationId, $readyPath, $goPath], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start acceptance worker.');
    }
    $workers[] = compact('process', 'pipes', 'readyPath');
}

$deadline = microtime(true) + 10;
while (collect($workers)->contains(fn (array $worker): bool => ! is_file($worker['readyPath']))) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Acceptance workers did not become ready.');
    }
    usleep(10_000);
}
touch($goPath);

$workerResults = [];
foreach ($workers as $worker) {
    $stdout = stream_get_contents($worker['pipes'][1]);
    $stderr = stream_get_contents($worker['pipes'][2]);
    fclose($worker['pipes'][1]);
    fclose($worker['pipes'][2]);
    $exitCode = proc_close($worker['process']);
    if ($exitCode !== 0) {
        throw new RuntimeException('Acceptance worker failed: '.$stderr);
    }
    $workerResults[] = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
}

DB::reconnect();
$result = [
    'workers' => $workerResults,
    'acceptances' => DB::table('proposal_acceptances')->where('proposal_version_id', $proposalId)->count(),
    'proposal_status' => DB::table('proposal_versions')->where('id', $proposalId)->value('status'),
    'negotiation_status' => DB::table('negotiations')->where('id', $negotiationId)->value('status'),
    'audit_events' => DB::table('audit_events')->where('event_type', 'proposal.accepted')->count(),
];

foreach ([$goPath, ...array_column($workers, 'readyPath')] as $path) {
    if (is_file($path)) {
        unlink($path);
    }
}
rmdir($syncPath);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($result['acceptances'] === 2 && $result['proposal_status'] === 'accepted' && $result['negotiation_status'] === 'accepted' && $result['audit_events'] === 2 ? 0 : 1);
