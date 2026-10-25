<?php

namespace Tests\Feature;

use App\Domain\Catalog\CuratedSkinCatalogImporter;
use DomainException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CuratedSkinCatalogImporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
    }

    public function test_command_imports_the_reviewed_manifest_idempotently(): void
    {
        self::assertSame(0, Artisan::call('catalog:import-skins'));
        self::assertDatabaseCount('catalog_items', 231);
        self::assertDatabaseHas('catalog_items', ['canonical_name' => "Cthulu's Revenge", 'is_active' => true]);
        self::assertDatabaseHas('catalog_items', ['canonical_name' => "Heaven's Savior", 'is_active' => true]);

        self::assertSame(0, Artisan::call('catalog:import-skins'));
        self::assertDatabaseCount('catalog_items', 231);
        self::assertStringContainsString('0 created, 231 matched', Artisan::output());
        self::assertDatabaseCount('audit_events', 1);
        self::assertDatabaseHas('audit_events', ['event_type' => 'catalog.skin_manifest.imported']);
    }

    public function test_existing_alias_is_matched_without_creating_a_duplicate(): void
    {
        $categoryId = $this->category();
        DB::table('catalog_items')->insert([
            'public_id' => (string) Str::uuid(), 'category_id' => $categoryId,
            'canonical_name' => 'Legacy Cthulu', 'aliases' => json_encode(["Cthulu's Revenge"]),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(CuratedSkinCatalogImporter::class)->import();

        self::assertSame(['created' => 230, 'matched' => 1, 'total' => 231], $result);
        self::assertDatabaseCount('catalog_items', 231);
    }

    public function test_ambiguous_alias_rolls_back_the_import(): void
    {
        $categoryId = $this->category();
        foreach (['First', 'Second'] as $name) {
            DB::table('catalog_items')->insert([
                'public_id' => (string) Str::uuid(), 'category_id' => $categoryId,
                'canonical_name' => $name, 'aliases' => json_encode(["Cthulu's Revenge"]),
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->expectException(DomainException::class);
        try {
            app(CuratedSkinCatalogImporter::class)->import();
        } finally {
            self::assertDatabaseCount('catalog_items', 2);
        }
    }

    private function category(): int
    {
        return DB::table('catalog_categories')->insertGetId([
            'public_id' => (string) Str::uuid(), 'key' => 'skin', 'name' => 'Skins',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
