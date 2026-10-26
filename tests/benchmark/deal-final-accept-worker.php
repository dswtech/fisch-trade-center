<?php

use App\Domain\Trade\DealService;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $userId, $dealId, $readyPath, $goPath] = $argv;
touch($readyPath);
$deadline = microtime(true) + 10;
while (! is_file($goPath)) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, 'Timed out waiting for deal concurrency start signal.');
        exit(1);
    }
    usleep(10_000);
}

try {
    $deal = app(DealService::class)->finalAccept(
        User::query()->findOrFail((int) $userId),
        Deal::query()->findOrFail((int) $dealId),
        true,
    );
    echo json_encode(['status' => $deal->status], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    $state = Deal::query()->find((int) $dealId);
    fwrite(STDERR, json_encode([
        'exception' => $error::class,
        'message' => $error->getMessage(),
        'dealStatus' => $state?->status,
        'expiresAt' => $state?->expires_at?->toIso8601String(),
        'now' => now()->toIso8601String(),
        'isFuture' => $state?->expires_at?->isFuture(),
        'acceptances' => $state ? \Illuminate\Support\Facades\DB::table('deal_acceptances')->where('deal_id', $state->id)->count() : null,
    ], JSON_THROW_ON_ERROR));
    exit(1);
}
