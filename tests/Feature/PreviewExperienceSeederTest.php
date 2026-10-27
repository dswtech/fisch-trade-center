<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PreviewExperienceSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PreviewExperienceSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
        (new DatabaseSeeder)->run();
    }

    public function test_preview_experience_is_idempotent_and_populates_core_player_surfaces(): void
    {
        (new PreviewExperienceSeeder)->run();
        $first = $this->counts();

        (new PreviewExperienceSeeder)->run();

        self::assertSame($first, $this->counts());
        self::assertGreaterThanOrEqual(18, DB::table('listings')->where('status', 'active')->count());
        self::assertSame(1, DB::table('negotiations')->where('public_id', $this->previewUuid('negotiation/player-preview'))->count());
        self::assertGreaterThanOrEqual(2, DB::table('inventory_entries')->join('users', 'users.id', '=', 'inventory_entries.user_id')->where('users.email', 'user@ftc.test')->count());
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return collect(['users', 'player_profiles', 'inventory_entries', 'listings', 'listing_versions', 'listing_version_items', 'negotiations', 'proposal_versions', 'proposal_items'])
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();
    }

    private function previewUuid(string $key): string
    {
        return \Ramsey\Uuid\Uuid::uuid5(\Ramsey\Uuid\Uuid::NAMESPACE_URL, 'fisch-trade-center/preview/'.$key)->toString();
    }
}
