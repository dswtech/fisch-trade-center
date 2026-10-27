<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Models\CatalogItem;
use App\Models\InventoryEntry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
    }

    public function test_public_dashboard_is_available_without_private_identity(): void
    {
        $this->get('/')->assertOk()->assertDontSee('private@example.test')->assertDontSee('SecretIGN')->assertSee('/build/assets/app.js?v=', false);
    }

    public function test_login_overlay_returns_to_a_safe_local_page_only(): void
    {
        User::create(['name' => 'Returning Player', 'email' => 'returning@example.test', 'password' => 'password123']);

        $this->post('/login', ['email' => 'returning@example.test', 'password' => 'password123', 'return_to' => '/catalog?q=Abyssal'])->assertRedirect('/catalog?q=Abyssal');
        $this->post('/logout');
        $this->post('/login', ['email' => 'returning@example.test', 'password' => 'password123', 'return_to' => '//evil.example'])->assertRedirect('/');
    }

    public function test_unverified_user_is_gated_from_private_phase_one_surfaces(): void
    {
        $user=User::create(['name'=>'Unverified','email'=>'u@example.test','password'=>'password123']);
        $this->actingAs($user)->get('/inventory')->assertRedirect('/email/verify');
        $this->actingAs($user)->get('/simulator')->assertRedirect('/email/verify');
        $this->actingAs($user)->get('/listings/create')->assertRedirect('/email/verify');
    }

    public function test_discovery_scope_excludes_owner_expired_and_inactive_listings(): void
    {
        $viewer=User::create(['name'=>'Viewer','email'=>'v@example.test','password'=>'password123']); $other=User::create(['name'=>'Other','email'=>'o@example.test','password'=>'password123']);
        Listing::create(['user_id'=>$viewer->id,'status'=>'active','published_at'=>now(),'expires_at'=>now()->addDay()]);
        Listing::create(['user_id'=>$other->id,'status'=>'active','published_at'=>now(),'expires_at'=>now()->subMinute()]);
        Listing::create(['user_id'=>$other->id,'status'=>'draft']);
        self::assertCount(0,Listing::discoverable($viewer->id)->get());
    }

    public function test_inventory_object_authorization_prevents_cross_user_updates(): void
    {
        [$owner,$item]=$this->playerAndItem('owner@example.test'); $attacker=User::create(['name'=>'Attacker','email'=>'attacker@example.test','password'=>'password123']); $attacker->forceFill(['email_verified_at'=>now()])->save();
        $entry=InventoryEntry::create(['user_id'=>$owner->id,'catalog_item_id'=>$item->id,'quantity'=>2]);
        $this->actingAs($attacker)->patch('/inventory/'.$entry->public_id,['quantity'=>9])->assertNotFound();
        self::assertSame(2,$entry->fresh()->quantity);
    }

    public function test_listing_publish_uses_fourteen_day_expiry_and_material_edit_versions(): void
    {
        [$user,$item]=$this->playerAndItem('listing@example.test'); $entry=InventoryEntry::create(['user_id'=>$user->id,'catalog_item_id'=>$item->id,'quantity'=>2]);
        $payload=['offered'=>[['inventoryEntryId'=>$entry->public_id,'quantity'=>1]],'wanted'=>[['catalogItemId'=>$item->public_id,'quantity'=>1]]];
        $this->actingAs($user)->post('/listings',$payload)->assertSessionHasNoErrors(); $listing=Listing::firstOrFail();
        self::assertEquals(14,now()->startOfDay()->diffInDays($listing->expires_at->startOfDay()));
        $this->actingAs($user)->patch('/listings/'.$listing->public_id,$payload)->assertSessionHasNoErrors();
        self::assertSame(2,$listing->fresh()->current_version); self::assertDatabaseCount('listing_versions',2); self::assertDatabaseCount('listing_version_items',4);
        $this->actingAs($user)->get('/listings/'.$listing->public_id.'/edit')->assertOk()->assertInertia(fn(Assert $page)=>$page->component('workspace')->where('section','listing-edit')->where('listings.0.id',$listing->public_id));
    }

    public function test_inventory_reduction_invalidates_dependent_listing(): void
    {
        [$user,$item]=$this->playerAndItem('inventory-invalidation@example.test');
        $entry=InventoryEntry::create(['user_id'=>$user->id,'catalog_item_id'=>$item->id,'quantity'=>2]);
        $payload=['offered'=>[['inventoryEntryId'=>$entry->public_id,'quantity'=>2]],'wanted'=>[['catalogItemId'=>$item->public_id,'quantity'=>1]]];
        $this->actingAs($user)->post('/listings',$payload)->assertSessionHasNoErrors();

        $this->actingAs($user)->patch('/inventory/'.$entry->public_id,['quantity'=>1])->assertSessionHasNoErrors();

        self::assertSame('invalidated',Listing::firstOrFail()->status);
    }

    public function test_private_mutations_are_rate_limited(): void
    {
        [$user]=$this->playerAndItem('rate-limit@example.test');
        foreach(range(1,60) as $attempt){
            $this->actingAs($user)->patch('/preferences',['locale'=>'id','theme'=>'dark'])->assertRedirect();
        }

        $this->actingAs($user)->patch('/preferences',['locale'=>'id','theme'=>'dark'])->assertStatus(429);
    }

    public function test_admin_routes_require_admin_and_audit_threshold_changes(): void
    {
        [$player]=$this->playerAndItem('player@example.test'); $this->actingAs($player)->patch('/admin/settings/fair-threshold',['fairThresholdPercent'=>12])->assertForbidden();
        $admin=User::create(['name'=>'Admin','email'=>'admin@example.test','password'=>'password123','role'=>'admin']); $admin->forceFill(['email_verified_at'=>now()])->save();
        $this->actingAs($admin)->patch('/admin/settings/fair-threshold',['fairThresholdPercent'=>12])->assertSessionHasNoErrors();
        self::assertDatabaseHas('audit_events',['event_type'=>'setting.fair_threshold.changed']);
    }

    public function test_idempotency_key_replays_listing_and_setting_mutations_once(): void
    {
        [$user,$item]=$this->playerAndItem('retry@example.test');
        $entry=InventoryEntry::create(['user_id'=>$user->id,'catalog_item_id'=>$item->id,'quantity'=>2]);
        $payload=['offered'=>[['inventoryEntryId'=>$entry->public_id,'quantity'=>1]],'wanted'=>[['catalogItemId'=>$item->public_id,'quantity'=>1]]];
        $headers=['X-Idempotency-Key'=>'123e4567-e89b-42d3-a456-426614174000'];
        $this->actingAs($user)->post('/listings',$payload,$headers)->assertRedirect();
        $this->actingAs($user)->post('/listings',$payload,$headers)->assertRedirect();
        self::assertDatabaseCount('listings',1);

        $admin=User::create(['name'=>'Admin','email'=>'retry-admin@example.test','password'=>'password123','role'=>'admin']);
        $admin->forceFill(['email_verified_at'=>now()])->save();
        $settingHeaders=['X-Idempotency-Key'=>'223e4567-e89b-42d3-a456-426614174000'];
        $this->actingAs($admin)->patch('/admin/settings/fair-threshold',['fairThresholdPercent'=>12],$settingHeaders)->assertRedirect();
        $this->actingAs($admin)->patch('/admin/settings/fair-threshold',['fairThresholdPercent'=>12],$settingHeaders)->assertRedirect();
        self::assertSame(1,DB::table('audit_events')->where('event_type','setting.fair_threshold.changed')->count());
    }

    public function test_idempotency_key_cannot_be_reused_for_another_route(): void
    {
        [$user,$item]=$this->playerAndItem('fingerprint@example.test');
        $key=['X-Idempotency-Key'=>'323e4567-e89b-42d3-a456-426614174000'];
        $this->actingAs($user)->post('/inventory',['catalogItemId'=>$item->public_id,'quantity'=>1],$key)->assertRedirect();
        $this->actingAs($user)->patch('/profile',['usn'=>'PlayerUSN','ign'=>'PlayerIGN'],$key)->assertConflict();
    }

    public function test_search_filters_current_listing_version_and_redacts_owner_identity(): void
    {
        $owner=User::create(['name'=>'Private Owner','email'=>'private-owner@example.test','password'=>'password123']);$owner->forceFill(['email_verified_at'=>now()])->save();
        DB::table('player_profiles')->insert(['user_id'=>$owner->id,'usn'=>'OwnerSecretUSN','ign'=>'OwnerSecretIGN','created_at'=>now(),'updated_at'=>now()]);
        $viewer=User::create(['name'=>'Viewer','email'=>'viewer@example.test','password'=>'password123']);$viewer->forceFill(['email_verified_at'=>now()])->save();
        $categoryId=DB::table('catalog_categories')->insertGetId(['public_id'=>$categoryPublicId=(string)Str::uuid(),'key'=>'rod_skin','name'=>'Rod Skin','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $boatCategoryId=DB::table('catalog_categories')->insertGetId(['public_id'=>(string)Str::uuid(),'key'=>'boat','name'=>'Boats','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $offered=CatalogItem::create(['category_id'=>$categoryId,'canonical_name'=>'Abyssal Current','is_active'=>true]);
        $wanted=CatalogItem::create(['category_id'=>$boatCategoryId,'canonical_name'=>'Tidebreaker','is_active'=>true]);
        $importId=DB::table('value_imports')->insertGetId(['public_id'=>(string)Str::uuid(),'source_key'=>'test','status'=>'valid','checksum'=>str_repeat('a',64),'source_timestamp'=>now(),'validation_errors'=>'[]','payload'=>'[]','imported_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $snapshotId=DB::table('value_snapshots')->insertGetId(['public_id'=>(string)Str::uuid(),'value_import_id'=>$importId,'version'=>'test-v1','checksum'=>str_repeat('b',64),'source_timestamp'=>now(),'published_at'=>now(),'published_by'=>$owner->id,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('catalog_item_values')->insert([['snapshot_id'=>$snapshotId,'catalog_item_id'=>$offered->id,'unit_value'=>2400],['snapshot_id'=>$snapshotId,'catalog_item_id'=>$wanted->id,'unit_value'=>3200]]);
        $entry=InventoryEntry::create(['user_id'=>$owner->id,'catalog_item_id'=>$offered->id,'quantity'=>2]);
        $this->actingAs($owner)->post('/listings',['offered'=>[['inventoryEntryId'=>$entry->public_id,'quantity'=>1]],'wanted'=>[['catalogItemId'=>$wanted->public_id,'quantity'=>1]]])->assertSessionHasNoErrors();

        $query=http_build_query(['offeredItem'=>$offered->public_id,'wantedItem'=>$wanted->public_id,'category'=>$categoryPublicId,'minValue'=>2400,'maxValue'=>2400,'freshness'=>'hour']);
        $response=$this->actingAs($viewer)->get('/search?'.$query);
        $response->assertOk()->assertDontSee('private-owner@example.test')->assertDontSee('Private Owner')->assertDontSee('OwnerSecretUSN')->assertDontSee('OwnerSecretIGN')->assertInertia(fn(Assert $page)=>$page->component('discovery-hub')->has('listings',1)->where('listings.0.offered','Abyssal Current')->where('listings.0.wanted','Tidebreaker')->where('listings.0.value',2400)->has('listings.0.offeredItems',1)->has('listings.0.wantedItems',1));
        $this->actingAs($viewer)->get('/search?'.http_build_query(['maxValue'=>2399]))->assertInertia(fn(Assert $page)=>$page->has('listings',0));
        $this->actingAs($owner)->get('/search?'.$query)->assertInertia(fn(Assert $page)=>$page->has('listings',0));
    }

    public function test_search_rejects_an_inverted_value_range(): void
    {
        $this->get('/search?minValue=2400&maxValue=2399')->assertRedirect()->assertSessionHasErrors('maxValue');
    }

    public function test_discovery_cursor_is_deterministic_for_equal_publish_times(): void
    {
        $viewer=User::create(['name'=>'Viewer','email'=>'cursor-viewer@example.test','password'=>'password123']);
        $owner=User::create(['name'=>'Owner','email'=>'cursor-owner@example.test','password'=>'password123']);
        foreach(range(1,30) as $index){Listing::create(['user_id'=>$owner->id,'status'=>'active','published_at'=>'2026-10-24 12:00:00','expires_at'=>'2026-11-07 12:00:00']);}

        $first=Listing::discoverable($viewer->id)->cursorPaginate(10);
        $second=Listing::discoverable($viewer->id)->cursorPaginate(10,['*'],'cursor',$first->nextCursor());
        $firstIds=collect($first->items())->pluck('id');$secondIds=collect($second->items())->pluck('id');

        self::assertCount(10,$firstIds);self::assertCount(10,$secondIds);self::assertTrue($firstIds->intersect($secondIds)->isEmpty());
        self::assertSame(range(30,21),$firstIds->all());self::assertSame(range(20,11),$secondIds->all());
    }

    private function playerAndItem(string $email): array
    {
        $user=User::create(['name'=>'Player','email'=>$email,'password'=>'password123']); $user->forceFill(['email_verified_at'=>now()])->save();
        DB::table('player_profiles')->insert(['user_id'=>$user->id,'usn'=>'TestUSN','ign'=>'TestIGN','created_at'=>now(),'updated_at'=>now()]);
        $categoryId=DB::table('catalog_categories')->insertGetId(['public_id'=>(string)Str::uuid(),'key'=>'rod_skin','name'=>'Rod Skin','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        return [$user,CatalogItem::create(['category_id'=>$categoryId,'canonical_name'=>'Abyssal Current','is_active'=>true])];
    }
}
