<?php

namespace Database\Seeders;

use App\Models\CatalogItem;
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

        $user = User::create(['public_id' => (string) Str::uuid(), 'name' => 'E2E Angler', 'email' => 'e2e@example.test', 'password' => 'E2ePassword123!']);
        $user->forceFill(['email_verified_at' => now()])->save();

        foreach ([
            ['key' => 'skin', 'name' => 'Skins', 'item' => 'Abyssal Current'],
            ['key' => 'boat', 'name' => 'Boats', 'item' => 'Tidebreaker'],
            ['key' => 'fishing_rod', 'name' => 'Fishing Rods', 'item' => 'Aurora Rod'],
        ] as $fixture) {
            $categoryId = DB::table('catalog_categories')->insertGetId([
                'public_id' => (string) Str::uuid(), 'key' => $fixture['key'], 'name' => $fixture['name'],
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            CatalogItem::create([
                'public_id' => (string) Str::uuid(), 'category_id' => $categoryId, 'canonical_name' => $fixture['item'],
                'aliases' => [], 'provenance' => ['kind' => 'synthetic_test_fixture'], 'is_active' => true,
            ]);
        }
    }
}
