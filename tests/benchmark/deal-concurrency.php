<?php

use App\Models\Deal;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('benchmark') || DB::connection()->getDatabaseName() !== 'fisch_trade_benchmark') {
    throw new RuntimeException('Deal concurrency test refuses to run outside the disposable benchmark database.');
}

$negotiation = DB::table('negotiations')->where('status', 'accepted')->first();
$proposal = DB::table('proposal_versions')->where('negotiation_id', $negotiation->id)->where('status', 'accepted')->first();
$now = now();
foreach ([$negotiation->initiator_id, $negotiation->owner_id] as $userId) {
    DB::table('player_profiles')->insert([
        'user_id' => $userId, 'usn' => 'RaceUSN-'.$userId, 'ign' => 'RaceIGN-'.$userId, 'created_at' => $now, 'updated_at' => $now,
    ]);
}
$preDealId = DB::table('pre_deal_versions')->insertGetId([
    'public_id' => (string) Str::uuid(), 'negotiation_id' => $negotiation->id, 'proposal_version_id' => $proposal->id,
    'proposer_id' => $negotiation->initiator_id, 'version' => 1, 'server_type' => 'public_server', 'status' => 'accepted',
    'fee_quote' => 0, 'expires_at' => $now->copy()->addMinutes(30), 'created_at' => $now, 'updated_at' => $now,
]);
DB::table('pre_deal_acceptances')->insert([
    ['pre_deal_version_id' => $preDealId, 'user_id' => $negotiation->initiator_id, 'accepted_at' => $now],
    ['pre_deal_version_id' => $preDealId, 'user_id' => $negotiation->owner_id, 'accepted_at' => $now],
]);
$deal = Deal::create([
    'negotiation_id' => $negotiation->id, 'proposal_version_id' => $proposal->id, 'pre_deal_version_id' => $preDealId,
    'status' => 'awaiting_final_acceptance', 'fee_quote' => 0, 'expires_at' => $now->copy()->addMinutes(30),
]);

DB::unprepared('CREATE FUNCTION benchmark_pause_deal_acceptance() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN PERFORM pg_sleep(0.35); RETURN NEW; END; $$');
DB::unprepared('CREATE TRIGGER benchmark_pause_deal_acceptance BEFORE INSERT ON deal_acceptances FOR EACH ROW EXECUTE FUNCTION benchmark_pause_deal_acceptance()');
DB::disconnect();

$syncPath = __DIR__.'/.deal-concurrency';
if (! is_dir($syncPath) && ! mkdir($syncPath)) {
    throw new RuntimeException('Could not create deal concurrency sync directory.');
}
$goPath = $syncPath.'/go';
$workers = [];
foreach ([$negotiation->initiator_id, $negotiation->owner_id] as $index => $userId) {
    $readyPath = $syncPath.'/ready-'.$index;
    $pipes = [];
    $process = proc_open([PHP_BINARY, __DIR__.'/deal-final-accept-worker.php', (string) $userId, (string) $deal->id, $readyPath, $goPath], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start final acceptance worker.');
    }
    $workers[] = compact('process', 'pipes', 'readyPath');
}

$deadline = microtime(true) + 10;
while (collect($workers)->contains(fn (array $worker): bool => ! is_file($worker['readyPath']))) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Final acceptance workers did not become ready.');
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
    $workerResults[] = $exitCode === 0
        ? json_decode($stdout, true, flags: JSON_THROW_ON_ERROR)
        : ['error' => trim($stderr)];
}

DB::reconnect();
$result = [
    'workers' => $workerResults,
    'acceptances' => DB::table('deal_acceptances')->where('deal_id', $deal->id)->count(),
    'deal_status' => DB::table('deals')->where('id', $deal->id)->value('status'),
    'identity_snapshots' => DB::table('deal_identity_snapshots')->where('deal_id', $deal->id)->count(),
    'reveal_entitlements' => DB::table('reveal_entitlements')->where('deal_id', $deal->id)->count(),
    'reveal_audits' => DB::table('audit_events')->where('event_type', 'deal.revealed')->where('subject_public_id', $deal->public_id)->count(),
];

foreach ([$goPath, ...array_column($workers, 'readyPath')] as $path) {
    if (is_file($path)) {
        unlink($path);
    }
}
rmdir($syncPath);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
$passed = collect($workerResults)->doesntContain(fn (array $worker): bool => isset($worker['error']))
    && $result['acceptances'] === 2 && $result['deal_status'] === 'revealed'
    && $result['identity_snapshots'] === 2 && $result['reveal_entitlements'] === 2 && $result['reveal_audits'] === 1;
exit($passed ? 0 : 1);
