<?php

namespace Tests\Feature;

use App\Domain\Catalog\CatalogItemImage;
use App\Domain\Catalog\CatalogValueCsvImportService;
use App\Models\CatalogItem;
use App\Models\Listing;
use App\Models\ListingVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CatalogValueCsvImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
    }

    public function test_admin_stages_csv_without_mutating_catalog_and_invalid_files_are_rejected(): void
    {
        $admin = $this->admin();
        $csv = "item_key,category,name,aliases,value,offer_range,status\n,rod_skin,Aurora Skin,Aurora|Aurora Rod Skin,2400.50,2300-2500,active\n";

        $this->actingAs($admin)->post('/admin/catalog-value-imports', ['file' => UploadedFile::fake()->createWithContent('catalog.csv', $csv)])->assertRedirect();

        self::assertDatabaseCount('catalog_items', 0);
        self::assertDatabaseHas('value_imports', ['source_key' => 'admin-csv', 'status' => 'valid']);
        $payload = json_decode(DB::table('value_imports')->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $payload['preview']['created']);
        self::assertTrue(Str::isUuid($payload['rows'][0]['item_key']));

        $this->actingAs($admin)->post('/admin/catalog-value-imports', ['file' => UploadedFile::fake()->createWithContent('bad.csv', "wrong,header\na,b\n")])->assertRedirect();
        self::assertDatabaseHas('value_imports', ['status' => 'invalid']);
    }

    public function test_partial_publish_carries_omitted_values_clears_blank_values_and_requires_warning_confirmation(): void
    {
        $admin = $this->admin();
        [$skin, $boat, $other] = $this->baseline($admin);
        $csv = "item_key,category,name,aliases,value,offer_range,status\n"
            ."{$skin->public_id},rod_skin,Abyssal Current,Abyssal,150,140-160,active\n"
            ."{$boat->public_id},boat,Tidebreaker,,,,active\n"
            .",relic_set,Ancient Relic Set,Ancient Set,300,,inactive\n";
        $this->actingAs($admin)->post('/admin/catalog-value-imports', ['file' => UploadedFile::fake()->createWithContent('catalog.csv', $csv)])->assertRedirect();
        $import = DB::table('value_imports')->where('source_key', 'admin-csv')->latest('id')->first();

        $this->actingAs($admin)->post("/admin/catalog-value-imports/{$import->public_id}/publish")->assertStatus(422);
        $this->actingAs($admin)->post("/admin/catalog-value-imports/{$import->public_id}/publish", ['confirmWarnings' => true])->assertRedirect();

        $snapshotId = DB::table('value_snapshots')->where('value_import_id', $import->id)->value('id');
        self::assertDatabaseHas('catalog_item_values', ['snapshot_id' => $snapshotId, 'catalog_item_id' => $skin->id, 'unit_value' => 150]);
        self::assertDatabaseMissing('catalog_item_values', ['snapshot_id' => $snapshotId, 'catalog_item_id' => $boat->id]);
        self::assertDatabaseHas('catalog_item_values', ['snapshot_id' => $snapshotId, 'catalog_item_id' => $other->id, 'unit_value' => 50]);
        self::assertDatabaseHas('catalog_items', ['canonical_name' => 'Ancient Relic Set', 'is_active' => false]);

        $this->actingAs($admin)->post("/admin/catalog-value-imports/{$import->public_id}/publish", ['confirmWarnings' => true])->assertRedirect();
        self::assertSame(1, DB::table('value_snapshots')->where('value_import_id', $import->id)->count());
    }

    public function test_template_exports_stable_keys_and_non_admin_cannot_upload(): void
    {
        $admin = $this->admin();
        [$skin] = $this->baseline($admin);
        $player = User::create(['name' => 'Player', 'email' => 'player-csv@example.test', 'password' => 'password123']);
        $player->forceFill(['email_verified_at' => now()])->save();
        $csv = "item_key,category,name,aliases,value,offer_range,status\n,other,Thing,,10,,active\n";

        $this->actingAs($player)->post('/admin/catalog-value-imports', ['file' => UploadedFile::fake()->createWithContent('catalog.csv', $csv)])->assertForbidden();
        $response = $this->actingAs($admin)->get('/admin/catalog-value-imports/template')->assertOk();
        $content = $response->streamedContent();
        self::assertStringContainsString($skin->public_id, $content);
        self::assertStringContainsString('rod_skin', $content);
    }

    public function test_publish_rolls_back_catalog_and_snapshot_when_value_insert_fails(): void
    {
        $admin = $this->admin();
        $csv = "item_key,category,name,aliases,value,offer_range,status\n,other,Rollback Item,,10,,active\n";
        $this->actingAs($admin)->post('/admin/catalog-value-imports', ['file' => UploadedFile::fake()->createWithContent('rollback.csv', $csv)])->assertRedirect();
        $import = DB::table('value_imports')->latest('id')->first();
        DB::unprepared("CREATE TRIGGER fail_catalog_values BEFORE INSERT ON catalog_item_values BEGIN SELECT RAISE(ABORT, 'forced publish failure'); END");

        try {
            app(CatalogValueCsvImportService::class)->publish($import->public_id, $admin, false);
            self::fail('Publish should fail inside the transaction.');
        } catch (\Illuminate\Database\QueryException) {
            self::assertDatabaseMissing('catalog_items', ['canonical_name' => 'Rollback Item']);
            self::assertSame(0, DB::table('value_snapshots')->where('value_import_id', $import->id)->count());
            self::assertSame(0, DB::table('audit_events')->where('event_type', 'catalog_value_import.published')->count());
        }
    }

    public function test_alias_matching_duplicate_ambiguous_category_and_decimal_validation(): void
    {
        $admin = $this->admin();
        $categoryId = $this->category('rod_skin', 'Rod Skin');
        $item = CatalogItem::create(['category_id' => $categoryId, 'canonical_name' => 'Abyssal Current', 'aliases' => ['Deep Current'], 'is_active' => true]);
        $aliasCsv = "item_key,category,name,aliases,value,offer_range,status\n,rod_skin,Deep Current,,10.25,,active\n";
        $this->actingAs($admin)->post('/admin/catalog-value-imports', ['file' => UploadedFile::fake()->createWithContent('alias.csv', $aliasCsv)])->assertRedirect();
        $payload = json_decode(DB::table('value_imports')->latest('id')->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($item->public_id, $payload['rows'][0]['item_key']);
        self::assertSame('10.25', $payload['rows'][0]['value']);

        CatalogItem::create(['category_id' => $categoryId, 'canonical_name' => 'Second Skin', 'aliases' => ['Common Skin'], 'is_active' => true]);
        CatalogItem::create(['category_id' => $categoryId, 'canonical_name' => 'Third Skin', 'aliases' => ['Common Skin'], 'is_active' => true]);
        $invalidCsv = "item_key,category,name,aliases,value,offer_range,status\n"
            .",rod_skin,Common Skin,,10.123,,active\n"
            .",invalid,Duplicate,,, ,active\n"
            .",invalid,Duplicate,,,,active\n";
        $this->actingAs($admin)->post('/admin/catalog-value-imports', ['file' => UploadedFile::fake()->createWithContent('invalid.csv', $invalidCsv)])->assertRedirect();
        $import = DB::table('value_imports')->latest('id')->first();
        $codes = collect(json_decode($import->validation_errors, true, flags: JSON_THROW_ON_ERROR))->pluck('code');
        self::assertSame('invalid', $import->status);
        self::assertTrue($codes->contains('ambiguous_catalog_item'));
        self::assertTrue($codes->contains('invalid_value'));
        self::assertTrue($codes->contains('invalid_category'));
        self::assertTrue($codes->contains('duplicate_name'));
    }

    public function test_template_escapes_spreadsheet_formula_prefixes(): void
    {
        $admin = $this->admin();
        CatalogItem::create(['category_id' => $this->category('other', 'Lain-lain'), 'canonical_name' => '=DANGEROUS()', 'aliases' => ['+Alias'], 'is_active' => true]);

        $content = $this->actingAs($admin)->get('/admin/catalog-value-imports/template')->assertOk()->streamedContent();

        self::assertStringContainsString("'=DANGEROUS()", $content);
        self::assertStringContainsString("'+Alias", $content);
    }

    public function test_rod_assignment_and_thumbnail_fallback_upload_and_delete(): void
    {
        Storage::fake('public');
        config()->set('catalog.media_disk', 'public');
        $admin = $this->admin();
        $categoryId = $this->category('rod_skin', 'Rod Skin');
        $item = CatalogItem::create(['category_id' => $categoryId, 'canonical_name' => 'Aurora Skin', 'is_active' => true]);
        self::assertStringEndsWith('/images/categories/rod_skin.svg', CatalogItemImage::url(null, 'rod_skin'));
        $this->actingAs($admin)->post('/admin/rods', ['name' => 'Aurora Rod'])->assertRedirect();
        $rod = DB::table('rods')->where('name', 'Aurora Rod')->first();
        $this->actingAs($admin)->patch("/admin/catalog-items/{$item->public_id}/rod", ['rodId' => $rod->public_id])->assertRedirect();
        self::assertDatabaseHas('catalog_items', ['id' => $item->id, 'rod_id' => $rod->id]);
        $this->actingAs($admin)->patch("/admin/rods/{$rod->public_id}", ['name' => 'Aurora Rod Prime', 'isActive' => false])->assertRedirect();
        self::assertDatabaseHas('rods', ['id' => $rod->id, 'name' => 'Aurora Rod Prime', 'is_active' => false]);
        self::assertDatabaseHas('audit_events', ['event_type' => 'rod.updated', 'subject_public_id' => $rod->public_id]);
        $this->actingAs($admin)->patch("/admin/catalog-items/{$item->public_id}/rod", ['rodId' => $rod->public_id])->assertSessionHasErrors('rodId');
        $this->actingAs($admin)->patch("/admin/catalog-items/{$item->public_id}", ['aliasesText' => 'Aurora|Northern Light', 'isActive' => false])->assertRedirect();
        self::assertSame(['Aurora', 'Northern Light'], $item->fresh()->aliases);
        self::assertFalse($item->fresh()->is_active);

        $this->actingAs($admin)->post("/admin/catalog-items/{$item->public_id}/thumbnail", ['thumbnail' => UploadedFile::fake()->createWithContent('thumb.txt', 'not an image')])->assertSessionHasErrors('thumbnail');
        $this->actingAs($admin)->post("/admin/catalog-items/{$item->public_id}/thumbnail", ['thumbnail' => UploadedFile::fake()->create('too-large.png', 2049, 'image/png')])->assertSessionHasErrors('thumbnail');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2n2kAAAAASUVORK5CYII=');
        $this->actingAs($admin)->post("/admin/catalog-items/{$item->public_id}/thumbnail", ['thumbnail' => UploadedFile::fake()->createWithContent('thumb.png', $png)])->assertRedirect();
        $path = $item->fresh()->thumbnail_path;
        self::assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        self::assertStringNotContainsString('thumb.png', $path);

        $this->actingAs($admin)->post("/admin/catalog-items/{$item->public_id}/thumbnail", ['thumbnail' => UploadedFile::fake()->createWithContent('replacement.png', $png)])->assertRedirect();
        $replacement = $item->fresh()->thumbnail_path;
        self::assertNotSame($path, $replacement);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertExists($replacement);

        $this->actingAs($admin)->delete("/admin/catalog-items/{$item->public_id}/thumbnail")->assertRedirect();
        self::assertNull($item->fresh()->thumbnail_path);
        Storage::disk('public')->assertMissing($replacement);
    }

    public function test_approved_fishing_rod_cleanup_requires_exact_counts_and_deletes_transactionally(): void
    {
        $owner = $this->admin();
        $categoryId = $this->category('fishing_rod', 'Fishing Rods');
        $item = CatalogItem::create(['category_id' => $categoryId, 'canonical_name' => 'Legacy Fishing Rod', 'is_active' => true]);
        $importId = DB::table('value_imports')->insertGetId(['public_id' => (string) Str::uuid(), 'source_key' => 'e2e-fixture', 'status' => 'valid', 'checksum' => hash('sha256', 'cleanup'), 'source_timestamp' => now(), 'validation_errors' => '[]', 'payload' => '[]', 'imported_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $snapshotId = DB::table('value_snapshots')->insertGetId(['public_id' => (string) Str::uuid(), 'value_import_id' => $importId, 'version' => 'cleanup-fixture', 'checksum' => hash('sha256', 'cleanup-snapshot'), 'source_timestamp' => now(), 'published_at' => now(), 'published_by' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('catalog_item_values')->insert(['snapshot_id' => $snapshotId, 'catalog_item_id' => $item->id, 'unit_value' => 1]);
        foreach (range(1, 26) as $index) {
            $listing = Listing::create(['user_id' => $owner->id, 'value_snapshot_id' => $snapshotId, 'status' => 'active', 'current_version' => 1, 'published_at' => now(), 'expires_at' => now()->addDay()]);
            $version = ListingVersion::create(['listing_id' => $listing->id, 'version' => 1, 'value_snapshot_id' => $snapshotId, 'offered_items' => [], 'wanted_items' => []]);
            $version->items()->create(['catalog_item_id' => $item->id, 'side' => 'wanted', 'quantity' => 1, 'unit_value' => 1]);
        }

        self::assertSame(0, Artisan::call('catalog:remove-fishing-rods', ['--confirm' => true]));
        self::assertDatabaseMissing('catalog_items', ['id' => $item->id]);
        self::assertDatabaseMissing('catalog_categories', ['key' => 'fishing_rod']);
        self::assertDatabaseMissing('value_snapshots', ['id' => $snapshotId]);
        self::assertSame(0, DB::table('listings')->count());
    }

    /** @return array{CatalogItem,CatalogItem,CatalogItem} */
    private function baseline(User $admin): array
    {
        $skin = CatalogItem::create(['category_id' => $this->category('rod_skin', 'Rod Skin'), 'canonical_name' => 'Abyssal Current', 'aliases' => [], 'is_active' => true]);
        $boat = CatalogItem::create(['category_id' => $this->category('boat', 'Boats'), 'canonical_name' => 'Tidebreaker', 'aliases' => [], 'is_active' => true]);
        $other = CatalogItem::create(['category_id' => $this->category('other', 'Lain-lain'), 'canonical_name' => 'Golden Bobber', 'aliases' => [], 'is_active' => true]);
        $importId = DB::table('value_imports')->insertGetId(['public_id' => (string) Str::uuid(), 'source_key' => 'baseline', 'status' => 'valid', 'checksum' => hash('sha256', 'baseline'), 'source_timestamp' => now(), 'validation_errors' => '[]', 'payload' => '[]', 'imported_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $snapshotId = DB::table('value_snapshots')->insertGetId(['public_id' => (string) Str::uuid(), 'value_import_id' => $importId, 'version' => 'baseline', 'checksum' => hash('sha256', 'baseline-snapshot'), 'source_timestamp' => now(), 'published_at' => now(), 'published_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('catalog_item_values')->insert([
            ['snapshot_id' => $snapshotId, 'catalog_item_id' => $skin->id, 'unit_value' => 100],
            ['snapshot_id' => $snapshotId, 'catalog_item_id' => $boat->id, 'unit_value' => 200],
            ['snapshot_id' => $snapshotId, 'catalog_item_id' => $other->id, 'unit_value' => 50],
        ]);

        return [$skin, $boat, $other];
    }

    private function category(string $key, string $name): int
    {
        return DB::table('catalog_categories')->where('key', $key)->value('id') ?: DB::table('catalog_categories')->insertGetId([
            'public_id' => (string) Str::uuid(), 'key' => $key, 'name' => $name,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function admin(): User
    {
        $admin = User::create(['name' => 'Admin', 'email' => Str::uuid().'@example.test', 'password' => 'password123', 'role' => 'admin']);
        $admin->forceFill(['email_verified_at' => now()])->save();
        return $admin;
    }
}
