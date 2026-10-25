<?php

namespace Tests\Feature;

use App\Domain\Value\ValueImportService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\User;
use Tests\TestCase;

final class ValueImportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
        config()->set('value_source.enabled', true);
        config()->set('value_source.csv_url', 'https://example.test/values.csv');
        config()->set('value_source.gid', '1079603703');
        config()->set('value_source.category', 'skin');
        config()->set('value_source.headers.name', 'Name');
        config()->set('value_source.headers.value', 'Value');
        config()->set('value_source.headers.offer_range', 'Offer Range');
    }

    public function test_command_stages_a_mapped_import_without_publishing_it(): void
    {
        $this->catalogItem('Abyssal Current', ['Abyssal']);
        Http::fake(['example.test/*' => Http::response("Name,Value,Offer Range\nAbyssal,2400,2300-2500", 200)]);

        self::assertSame(0, Artisan::call('values:import'));
        self::assertDatabaseHas('value_imports', ['source_key' => 'google-sheets-skins', 'status' => 'valid']);
        self::assertDatabaseCount('value_snapshots', 0);
        $row = json_decode(DB::table('value_imports')->value('payload'), true, flags: JSON_THROW_ON_ERROR)[0];
        self::assertNotEmpty($row['catalog_item_public_id']);
    }

    public function test_daily_import_schedule_is_registered(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();
        self::assertStringContainsString('values:import', $output);
        self::assertSame('02:00', config('value_source.schedule'));
        self::assertSame('Asia/Jakarta', config('value_source.timezone'));
    }

    public function test_admin_manually_publishes_a_valid_production_import(): void
    {
        $this->catalogItem('Abyssal Current');
        Http::fake(['example.test/*' => Http::response("Name,Value,Offer Range\nAbyssal Current,2400,2300-2500", 200)]);
        $result = app(ValueImportService::class)->import();
        $admin = User::create(['name' => 'Admin', 'email' => 'value-admin@example.test', 'password' => 'password123', 'role' => 'admin']);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->post('/admin/value-snapshots/publish', ['importId' => $result['public_id']])->assertRedirect();

        self::assertDatabaseCount('value_snapshots', 1);
        self::assertDatabaseHas('catalog_item_values', ['unit_value' => 2400, 'offer_range' => '2300-2500']);
    }

    public function test_decimal_source_value_is_staged_and_published_without_rounding(): void
    {
        $this->catalogItem('Stygian Winter');
        Http::fake(['example.test/*' => Http::response("Name,Value,Offer Range\nStygian Winter,7.5,7.5 - 9", 200)]);
        $result = app(ValueImportService::class)->import();
        $admin = User::create(['name' => 'Admin', 'email' => 'decimal-admin@example.test', 'password' => 'password123', 'role' => 'admin']);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->post('/admin/value-snapshots/publish', ['importId' => $result['public_id']])->assertRedirect();

        self::assertDatabaseHas('catalog_item_values', ['unit_value' => 7.5, 'offer_range' => '7.5 - 9']);
    }

    public function test_production_import_route_is_admin_only_and_idempotent(): void
    {
        $this->catalogItem('Abyssal Current');
        Http::fake(['example.test/*' => Http::response("Name,Value\nAbyssal Current,2400", 200)]);
        $player = User::create(['name' => 'Player', 'email' => 'value-player@example.test', 'password' => 'password123']);
        $player->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($player)->post('/admin/value-imports/production')->assertForbidden();

        $admin = User::create(['name' => 'Admin', 'email' => 'import-admin@example.test', 'password' => 'password123', 'role' => 'admin']);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $headers = ['X-Idempotency-Key' => '423e4567-e89b-42d3-a456-426614174000'];
        $this->actingAs($admin)->post('/admin/value-imports/production', [], $headers)->assertRedirect();
        $this->actingAs($admin)->post('/admin/value-imports/production', [], $headers)->assertRedirect();

        self::assertDatabaseCount('value_imports', 1);
        Http::assertSentCount(1);
    }

    public function test_unknown_and_ambiguous_catalog_mappings_are_rejected(): void
    {
        $this->catalogItem('First Skin', ['Shared']);
        $this->catalogItem('Second Skin', ['Shared']);
        Http::fake(['example.test/*' => Http::response("Name,Value\nUnknown,100\nShared,200", 200)]);

        $result = app(ValueImportService::class)->import();

        self::assertSame('invalid', $result['status']);
        self::assertContains('unmapped_catalog_item', array_column($result['errors'], 'code'));
        self::assertContains('ambiguous_catalog_item', array_column($result['errors'], 'code'));
        self::assertDatabaseCount('value_snapshots', 0);
    }

    public function test_anomaly_gate_accepts_exactly_twenty_five_percent_and_rejects_more(): void
    {
        $itemId = $this->catalogItem('Abyssal Current');
        $this->publishedValue($itemId, 100);

        Http::fake(['example.test/*' => Http::sequence()
            ->push("Name,Value\nAbyssal Current,125", 200)
            ->push("Name,Value\nAbyssal Current,126", 200)]);

        self::assertSame('valid', app(ValueImportService::class)->import()['status']);
        $rejected = app(ValueImportService::class)->import();
        self::assertSame('invalid', $rejected['status']);
        self::assertContains('anomalous_value_change', array_column($rejected['errors'], 'code'));
    }

    public function test_first_production_import_ignores_an_unrelated_fixture_snapshot(): void
    {
        $itemId = $this->catalogItem('Abyssal Current');
        $this->publishedValue($itemId, 100, 'e2e-fixture');
        Http::fake(['example.test/*' => Http::response("Name,Value\nAbyssal Current,1000", 200)]);

        $result = app(ValueImportService::class)->import();

        self::assertSame('valid', $result['status']);
        self::assertNotContains('anomalous_value_change', array_column($result['errors'], 'code'));
    }

    public function test_source_failure_is_recorded_without_replacing_the_published_snapshot(): void
    {
        $itemId = $this->catalogItem('Abyssal Current');
        $this->publishedValue($itemId, 100);
        Http::fake(['example.test/*' => Http::response('unavailable', 503)]);

        $result = app(ValueImportService::class)->import();

        self::assertSame('invalid', $result['status']);
        self::assertSame(1, DB::table('value_snapshots')->count());
        self::assertContains('source_fetch_failed', array_column($result['errors'], 'code'));
    }

    private function catalogItem(string $name, array $aliases = []): int
    {
        $categoryId = DB::table('catalog_categories')->where('key', 'skin')->value('id') ?: DB::table('catalog_categories')->insertGetId([
            'public_id' => (string) Str::uuid(), 'key' => 'skin', 'name' => 'Skins', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('catalog_items')->insertGetId([
            'public_id' => (string) Str::uuid(), 'category_id' => $categoryId, 'canonical_name' => $name,
            'aliases' => json_encode($aliases), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function publishedValue(int $itemId, int $value, string $sourceKey = 'google-sheets-skins'): void
    {
        $importId = DB::table('value_imports')->insertGetId([
            'public_id' => (string) Str::uuid(), 'source_key' => $sourceKey, 'status' => 'valid', 'checksum' => str_repeat('a', 64),
            'source_timestamp' => now(), 'validation_errors' => '[]', 'payload' => '[]', 'imported_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $snapshotId = DB::table('value_snapshots')->insertGetId([
            'public_id' => (string) Str::uuid(), 'value_import_id' => $importId, 'version' => 'baseline-'.Str::random(6),
            'checksum' => hash('sha256', Str::random()), 'source_timestamp' => now(), 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('catalog_item_values')->insert(['snapshot_id' => $snapshotId, 'catalog_item_id' => $itemId, 'unit_value' => $value]);
    }
}
