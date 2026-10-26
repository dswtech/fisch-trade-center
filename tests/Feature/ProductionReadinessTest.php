<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ProductionReadinessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
        config([
            'operations.storage_path' => storage_path('app/public'),
            'operations.min_free_disk_mb' => 0,
        ]);
    }

    public function test_liveness_and_readiness_are_separate_and_fail_closed(): void
    {
        Cache::forever((string) config('operations.heartbeat_key'), now()->getTimestamp());

        $this->get('/up')->assertOk();
        $this->get('/ready')->assertOk()->assertJson([
            'status' => 'ready',
            'checks' => ['database' => true, 'scheduler' => true, 'storage' => true, 'disk' => true],
        ]);

        Cache::forever((string) config('operations.heartbeat_key'), now()->subMinutes(4)->getTimestamp());
        $this->get('/ready')->assertStatus(503)->assertJsonPath('checks.scheduler', false);

        config(['operations.storage_path' => storage_path('missing-operations-path')]);
        $this->get('/ready')->assertStatus(503)->assertJsonPath('checks.storage', false)->assertJsonPath('checks.disk', false);

        config(['operations.storage_path' => storage_path('app/public'), 'operations.min_free_disk_mb' => 2_000_000_000]);
        $this->get('/ready')->assertStatus(503)->assertJsonPath('checks.storage', true)->assertJsonPath('checks.disk', false);
    }

    public function test_readiness_hides_database_failure_details(): void
    {
        Cache::forever((string) config('operations.heartbeat_key'), now()->getTimestamp());
        config(['database.connections.sqlite.database' => storage_path('missing/database.sqlite')]);
        DB::purge('sqlite');

        $this->get('/ready')->assertStatus(503)->assertJsonPath('checks.database', false)->assertDontSee('secret-hostname');
    }

    public function test_correlation_id_and_security_headers_are_applied_to_web_requests(): void
    {
        $correlationId = (string) Str::uuid();
        $response = $this->withHeader('X-Correlation-ID', $correlationId)->get('/up');

        $response->assertHeader('X-Correlation-ID', $correlationId)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $generated = $this->withHeader('X-Correlation-ID', 'not-a-uuid')->get('/up')->headers->get('X-Correlation-ID');
        self::assertTrue(Str::isUuid($generated));
    }

    public function test_production_https_adds_nonce_csp_and_hsts(): void
    {
        $this->app['env'] = 'production';

        $response = $this->withServerVariables(['HTTPS' => 'on'])->get('https://localhost/');

        $response->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $csp = (string) $response->headers->get('Content-Security-Policy');
        self::assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/", $csp);
        preg_match("/'nonce-([^']+)'/", $csp, $matches);
        $response->assertSee('nonce="'.$matches[1].'"', false);
    }

    public function test_scheduler_registers_heartbeat_and_guarded_retention(): void
    {
        $events = collect(Schedule::events());

        self::assertTrue($events->contains(fn ($event): bool => $event->description === 'operations:heartbeat'));
        self::assertTrue($events->contains(fn ($event): bool => str_contains((string) ($event->command ?? ''), 'ops:retention --execute')));

        Cache::forget((string) config('operations.heartbeat_key'));
        self::assertSame(0, Artisan::call('ops:heartbeat'));
        self::assertIsInt(Cache::get((string) config('operations.heartbeat_key')));
    }

    public function test_wallet_and_fee_remain_disabled(): void
    {
        self::assertFalse(config('phase_two.wallet_enabled'));
        self::assertFalse(config('phase_two.fee_enabled'));
    }

    public function test_routes_can_be_cached_for_atomic_release_deployments(): void
    {
        self::assertSame(0, Artisan::call('route:cache'));
        self::assertSame(0, Artisan::call('route:clear'));
    }
}
