<?php

namespace Database\Seeders;

use App\Models\CatalogItem;
use App\Models\InventoryEntry;
use App\Models\Listing;
use App\Models\ListingVersion;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('Synthetic fixtures may only be seeded in the testing environment.');
        }

        $this->call(PreviewAccountSeeder::class);

        $user = User::create(['public_id' => (string) Str::uuid(), 'name' => 'E2E Angler', 'email' => 'e2e@example.test', 'password' => 'E2ePassword123!']);
        $user->forceFill(['email_verified_at' => now()])->save();

        $items = collect([
            ['key' => 'rod_skin', 'name' => 'Rod Skin', 'item' => 'Abyssal Current'],
            ['key' => 'boat', 'name' => 'Boats', 'item' => 'Tidebreaker'],
            ['key' => 'relic_set', 'name' => 'Relic & Sets', 'item' => 'Ancient Relic Set'],
            ['key' => 'other', 'name' => 'Lain-lain', 'item' => 'Golden Bobber'],
        ])->mapWithKeys(function (array $fixture): array {
            $categoryId = DB::table('catalog_categories')->insertGetId([
                'public_id' => (string) Str::uuid(), 'key' => $fixture['key'], 'name' => $fixture['name'],
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $item = CatalogItem::create([
                'public_id' => (string) Str::uuid(), 'category_id' => $categoryId, 'canonical_name' => $fixture['item'],
                'aliases' => [], 'provenance' => ['kind' => 'synthetic_test_fixture'], 'is_active' => true,
            ]);
            return [$fixture['key'] => $item];
        });

        $owner = User::create(['public_id' => (string) Str::uuid(), 'name' => 'Synthetic Owner', 'email' => 'owner-fixture@example.test', 'password' => 'E2ePassword123!']);
        $owner->forceFill(['email_verified_at' => now()])->save();
        DB::table('player_profiles')->insert(['user_id' => $owner->id, 'usn' => 'SeedSecretUSN', 'ign' => 'SeedSecretIGN', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('player_profiles')->insert(['user_id' => $user->id, 'usn' => 'E2EPrivateUSN', 'ign' => 'E2EPrivateIGN', 'created_at' => now(), 'updated_at' => now()]);
        $ownerEntry = InventoryEntry::create(['public_id' => (string) Str::uuid(), 'user_id' => $owner->id, 'catalog_item_id' => $items['rod_skin']->id, 'quantity' => 30]);
        InventoryEntry::create(['public_id' => (string) Str::uuid(), 'user_id' => $user->id, 'catalog_item_id' => $items['rod_skin']->id, 'quantity' => 2]);
        $importId = DB::table('value_imports')->insertGetId(['public_id' => (string) Str::uuid(), 'source_key' => 'e2e-fixture', 'status' => 'valid', 'checksum' => str_repeat('a', 64), 'source_timestamp' => now(), 'validation_errors' => '[]', 'payload' => '[]', 'imported_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $snapshotId = DB::table('value_snapshots')->insertGetId(['public_id' => (string) Str::uuid(), 'value_import_id' => $importId, 'version' => 'e2e-v1', 'checksum' => str_repeat('b', 64), 'source_timestamp' => now(), 'published_at' => now(), 'published_by' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('catalog_item_values')->insert([
            ['snapshot_id' => $snapshotId, 'catalog_item_id' => $items['rod_skin']->id, 'unit_value' => 2400],
            ['snapshot_id' => $snapshotId, 'catalog_item_id' => $items['boat']->id, 'unit_value' => 3200],
        ]);

        foreach (range(1, 26) as $index) {
            $publishedAt = now()->subMinutes($index);
            $listing = Listing::create(['public_id' => (string) Str::uuid(), 'user_id' => $owner->id, 'value_snapshot_id' => $snapshotId, 'status' => 'active', 'current_version' => 1, 'published_at' => $publishedAt, 'expires_at' => $publishedAt->copy()->addDays(14)]);
            $version = ListingVersion::create(['listing_id' => $listing->id, 'version' => 1, 'value_snapshot_id' => $snapshotId, 'offered_items' => [['inventory_entry_id' => $ownerEntry->public_id, 'catalog_item_id' => $items['rod_skin']->id, 'quantity' => 1]], 'wanted_items' => [['catalog_item_id' => $items['boat']->id, 'quantity' => 1]], 'offered_value' => 2400, 'wanted_value' => 3200]);
            $version->items()->createMany([
                ['catalog_item_id' => $items['rod_skin']->id, 'inventory_entry_id' => $ownerEntry->id, 'side' => 'offered', 'quantity' => 1, 'unit_value' => 2400],
                ['catalog_item_id' => $items['boat']->id, 'side' => 'wanted', 'quantity' => 1, 'unit_value' => 3200],
            ]);
        }
    }
}
