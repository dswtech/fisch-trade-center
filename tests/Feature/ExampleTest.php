<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Models\CatalogItem;
use App\Models\InventoryEntry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $this->get('/')->assertOk()->assertDontSee('private@example.test')->assertDontSee('SecretIGN');
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
        self::assertSame(2,$listing->fresh()->current_version); self::assertDatabaseCount('listing_versions',2);
    }

    public function test_admin_routes_require_admin_and_audit_threshold_changes(): void
    {
        [$player]=$this->playerAndItem('player@example.test'); $this->actingAs($player)->patch('/admin/settings/fair-threshold',['fairThresholdPercent'=>12])->assertForbidden();
        $admin=User::create(['name'=>'Admin','email'=>'admin@example.test','password'=>'password123','role'=>'admin']); $admin->forceFill(['email_verified_at'=>now()])->save();
        $this->actingAs($admin)->patch('/admin/settings/fair-threshold',['fairThresholdPercent'=>12])->assertSessionHasNoErrors();
        self::assertDatabaseHas('audit_events',['event_type'=>'setting.fair_threshold.changed']);
    }

    public function test_admin_fixture_publish_is_immutable_and_idempotent(): void
    {
        $admin=User::create(['name'=>'Admin','email'=>'publisher@example.test','password'=>'password123','role'=>'admin']); $admin->forceFill(['email_verified_at'=>now()])->save();
        $this->actingAs($admin)->post('/admin/value-imports/fixture')->assertSessionHasNoErrors(); $import=DB::table('value_imports')->first();
        $this->actingAs($admin)->post('/admin/value-snapshots/publish',['importId'=>$import->public_id])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/value-snapshots/publish',['importId'=>$import->public_id])->assertSessionHasNoErrors();
        self::assertDatabaseCount('value_snapshots',1); self::assertDatabaseHas('audit_events',['event_type'=>'value_snapshot.published']);
    }

    private function playerAndItem(string $email): array
    {
        $user=User::create(['name'=>'Player','email'=>$email,'password'=>'password123']); $user->forceFill(['email_verified_at'=>now()])->save();
        $categoryId=DB::table('catalog_categories')->insertGetId(['public_id'=>(string)Str::uuid(),'key'=>'skin-'.Str::random(5),'name'=>'Skins','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        return [$user,CatalogItem::create(['category_id'=>$categoryId,'canonical_name'=>'Abyssal Current','is_active'=>true])];
    }
}
