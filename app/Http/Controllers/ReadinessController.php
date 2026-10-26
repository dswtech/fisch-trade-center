<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->databaseReady(),
            'scheduler' => $this->schedulerReady(),
            'storage' => $this->storageReady(),
            'disk' => $this->diskReady(),
        ];
        $ready = ! in_array(false, $checks, true);

        return response()->json(['status' => $ready ? 'ready' : 'unavailable', 'checks' => $checks], $ready ? 200 : 503);
    }

    private function databaseReady(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function schedulerReady(): bool
    {
        $heartbeat = Cache::get((string) config('operations.heartbeat_key'));

        return is_int($heartbeat) && $heartbeat >= now()->subSeconds((int) config('operations.heartbeat_max_age_seconds'))->getTimestamp();
    }

    private function storageReady(): bool
    {
        $path = (string) config('operations.storage_path');

        return is_dir($path) && is_writable($path);
    }

    private function diskReady(): bool
    {
        $bytes = @disk_free_space((string) config('operations.storage_path'));

        return $bytes !== false && $bytes >= ((int) config('operations.min_free_disk_mb') * 1024 * 1024);
    }
}
