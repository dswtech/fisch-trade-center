<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CatalogBrowsingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
    }

    public function test_guest_catalog_filters_sorts_and_paginates_before_slicing(): void
    {
        [$rodCategory, $boatCategory] = $this->categories();
        $items = collect();
        foreach (range(1, 45) as $index) {
            $items->push(CatalogItem::create([
                'category_id' => $index === 45 ? $boatCategory : $rodCategory,
                'canonical_name' => sprintf('Item %02d', $index),
                'aliases' => $index === 17 ? ['Moon Alias'] : [],
                'is_active' => true,
            ]));
        }
        $snapshot = $this->snapshot();
        foreach ($items->take(44) as $index => $item) {
            DB::table('catalog_item_values')->insert(['snapshot_id' => $snapshot, 'catalog_item_id' => $item->id, 'unit_value' => $index + 1, 'offer_range' => null]);
        }

        $this->get('/catalog')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('workspace')->where('section', 'catalog')->has('items', 40)
            ->where('items.0.name', 'Item 44')->where('pagination.total', 45)->where('pagination.lastPage', 2));
        $this->get('/catalog?page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 5)->where('items.4.name', 'Item 45')->where('items.4.value', null));
        $this->get('/catalog?q=moon+alias')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)->where('items.0.name', 'Item 17')->where('pagination.total', 1));
        $this->get('/catalog?q=rod_skin')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 40)->where('pagination.total', 44));
        $this->get('/catalog?category=boat')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)->where('items.0.name', 'Item 45'));
        $this->get('/catalog?sort=value_asc')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('items.0.name', 'Item 01')->where('catalogFilters.sort', 'value_asc'));
        $this->get('/catalog?sort=name_asc')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('items.0.name', 'Item 01')->where('catalogFilters.sort', 'name_asc'));
        $this->get('/catalog?sort=name_desc')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('items.0.name', 'Item 45')->where('catalogFilters.sort', 'name_desc'));
        $this->get('/catalog?page=99')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 0)->where('pagination.currentPage', 99)->where('pagination.total', 45));
    }

    public function test_invalid_catalog_filters_are_rejected(): void
    {
        $this->from('/catalog')->get('/catalog?category=weapon')->assertRedirect('/catalog')->assertSessionHasErrors('category');
        $this->from('/catalog')->get('/catalog?sort=random')->assertRedirect('/catalog')->assertSessionHasErrors('sort');
    }

    public function test_guest_can_open_active_detail_but_not_inactive_or_unknown_item(): void
    {
        [$rodCategory] = $this->categories();
        $active = CatalogItem::create(['category_id' => $rodCategory, 'canonical_name' => 'Aurora Skin', 'aliases' => ['Aurora'], 'is_active' => true]);
        $inactive = CatalogItem::create(['category_id' => $rodCategory, 'canonical_name' => 'Retired Skin', 'is_active' => false]);
        $snapshot = $this->snapshot('catalog-detail-v1');
        DB::table('catalog_item_values')->insert(['snapshot_id' => $snapshot, 'catalog_item_id' => $active->id, 'unit_value' => 2400.5, 'offer_range' => '2300-2500']);

        $this->get('/catalog/'.$active->public_id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('section', 'catalog-detail')->where('item.name', 'Aurora Skin')->where('item.categoryKey', 'rod_skin')
            ->where('item.aliases.0', 'Aurora')->where('item.rodName', 'Undefined Rod')->where('item.value', 2400.5)
            ->where('snapshot.version', 'catalog-detail-v1'));
        $this->get('/catalog/'.$inactive->public_id)->assertNotFound();
        $this->get('/catalog/'.Str::uuid())->assertNotFound();
    }

    public function test_pending_trade_action_counts_a_negotiation_once_and_excludes_terminal_state(): void
    {
        [$rodCategory] = $this->categories();
        $item = CatalogItem::create(['category_id' => $rodCategory, 'canonical_name' => 'Action Item', 'is_active' => true]);
        $viewer = $this->user('viewer-actions@example.test');
        $owner = $this->user('owner-actions@example.test');
        $listing = DB::table('listings')->insertGetId([
            'public_id' => (string) Str::uuid(), 'user_id' => $owner->id, 'status' => 'active', 'current_version' => 1,
            'published_at' => now(), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $negotiation = $this->negotiation($listing, $viewer, $owner, 'accepted');
        $proposal = DB::table('proposal_versions')->insertGetId([
            'public_id' => (string) Str::uuid(), 'negotiation_id' => $negotiation, 'proposer_id' => $viewer->id,
            'version' => 1, 'status' => 'accepted', 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('negotiations')->where('id', $negotiation)->update(['active_proposal_version' => 1]);
        $preDeal = DB::table('pre_deal_versions')->insertGetId([
            'public_id' => (string) Str::uuid(), 'negotiation_id' => $negotiation, 'proposal_version_id' => $proposal,
            'proposer_id' => $owner->id, 'version' => 1, 'server_type' => 'public_server', 'status' => 'active',
            'fee_quote' => 0, 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $dealPublicId = (string) Str::uuid();
        DB::table('deals')->insert([
            'public_id' => $dealPublicId, 'negotiation_id' => $negotiation, 'proposal_version_id' => $proposal,
            'pre_deal_version_id' => $preDeal, 'status' => 'awaiting_final_acceptance', 'fee_quote' => 0,
            'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $terminal = $this->negotiation($listing, $viewer, $owner, 'revealed');
        DB::table('negotiations')->where('id', $terminal)->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($viewer)->get('/catalog')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tradeActions.pendingCount', 1)
            ->has('tradeActions.items', 1)
            ->where('tradeActions.items.0.negotiationId', DB::table('negotiations')->where('id', $negotiation)->value('public_id'))
            ->where('tradeActions.items.0.alias', 'Angler B')
            ->where('tradeActions.items.0.stage', 'final_acceptance')
            ->where('tradeActions.items.0.href', '/deals/'.$dealPublicId)
            ->missing('tradeActions.items.0.email'));
    }

    public function test_trade_action_popover_payload_is_newest_first_and_limited_to_five(): void
    {
        [$rodCategory] = $this->categories();
        CatalogItem::create(['category_id' => $rodCategory, 'canonical_name' => 'Popover Item', 'is_active' => true]);
        $viewer = $this->user('viewer-popover@example.test');
        $owner = $this->user('owner-popover@example.test');
        $listing = DB::table('listings')->insertGetId([
            'public_id' => (string) Str::uuid(), 'user_id' => $owner->id, 'status' => 'active', 'current_version' => 1,
            'published_at' => now(), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $newestNegotiationPublicId = null;

        foreach (range(1, 6) as $index) {
            $activityAt = now()->subMinutes(7 - $index);
            $negotiation = $this->negotiation($listing, $viewer, $owner, 'proposed');
            $publicId = DB::table('negotiations')->where('id', $negotiation)->value('public_id');
            DB::table('negotiations')->where('id', $negotiation)->update(['active_proposal_version' => 1, 'last_activity_at' => $activityAt]);
            DB::table('proposal_versions')->insert([
                'public_id' => (string) Str::uuid(), 'negotiation_id' => $negotiation, 'proposer_id' => $owner->id,
                'version' => 1, 'status' => 'active', 'intent' => $index === 6 ? 'request_more_items' : 'proposal',
                'expires_at' => now()->addHour(), 'created_at' => $activityAt, 'updated_at' => $activityAt,
            ]);
            if ($index === 6) {
                $newestNegotiationPublicId = $publicId;
            }
        }

        $this->actingAs($viewer)->get('/catalog')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tradeActions.pendingCount', 6)
            ->has('tradeActions.items', 5)
            ->where('tradeActions.items.0.negotiationId', $newestNegotiationPublicId)
            ->where('tradeActions.items.0.stage', 'request_more_items')
            ->where('tradeActions.items.0.href', '/negotiations/'.$newestNegotiationPublicId));
    }

    /** @return array{int, int} */
    private function categories(): array
    {
        return [
            DB::table('catalog_categories')->insertGetId(['public_id' => (string) Str::uuid(), 'key' => 'rod_skin', 'name' => 'Rod Skin', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]),
            DB::table('catalog_categories')->insertGetId(['public_id' => (string) Str::uuid(), 'key' => 'boat', 'name' => 'Boats', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]),
        ];
    }

    private function snapshot(string $version = 'catalog-test-v1'): int
    {
        $import = DB::table('value_imports')->insertGetId([
            'public_id' => (string) Str::uuid(), 'source_key' => 'test', 'status' => 'valid', 'checksum' => hash('sha256', $version),
            'source_timestamp' => now(), 'validation_errors' => '[]', 'payload' => '[]', 'imported_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('value_snapshots')->insertGetId([
            'public_id' => (string) Str::uuid(), 'value_import_id' => $import, 'version' => $version, 'checksum' => hash('sha256', 'snapshot-'.$version),
            'source_timestamp' => now(), 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function user(string $email): User
    {
        $user = User::create(['name' => 'Player', 'email' => $email, 'password' => 'password123']);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function negotiation(int $listing, User $viewer, User $owner, string $status): int
    {
        return DB::table('negotiations')->insertGetId([
            'public_id' => (string) Str::uuid(), 'listing_id' => $listing, 'initiator_id' => $viewer->id, 'owner_id' => $owner->id,
            'status' => $status, 'initiator_alias' => 'Angler A', 'owner_alias' => 'Angler B', 'last_activity_at' => now(),
            'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
