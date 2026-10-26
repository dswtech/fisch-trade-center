<?php

use App\Domain\Trade\NegotiationService;
use App\Models\Negotiation;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $userId, $negotiationId, $readyPath, $goPath] = $argv;
touch($readyPath);
$deadline = microtime(true) + 10;
while (! is_file($goPath)) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, 'Timed out waiting for concurrency start signal.');
        exit(1);
    }
    usleep(10_000);
}

try {
    $proposal = app(NegotiationService::class)->accept(
        User::query()->findOrFail((int) $userId),
        Negotiation::query()->findOrFail((int) $negotiationId),
        1,
    );
    echo json_encode(['status' => $proposal->status], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage());
    exit(1);
}
