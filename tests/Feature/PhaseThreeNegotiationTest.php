<?php

namespace Tests\Feature;

use App\Domain\Trade\PhaseTwoExpiryService;
use App\Models\CatalogItem;
use App\Models\InventoryEntry;
use App\Models\Listing;
use App\Models\Negotiation;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PhaseThreeNegotiationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
    }

    public function test_recommendations_start_a_private_negotiation_without_exposing_identity(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['initiator'])->get('/recommendations/'.$fixture['listing']->public_id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('trade')
                ->where('section', 'recommendations')
                ->where('listing.alias', 'Anonymous trader')
                ->has('recommendations')
                ->where('recommendations.0.items.0.inventoryEntryId', $fixture['initiatorEntry']->public_id)
                ->where('recommendations.0.perspective', 'viewer')
                ->missing('listing.email')
                ->missing('listing.usn'));

        $response = $this->actingAs($fixture['initiator'])->post('/negotiations', [
            'listingId' => $fixture['listing']->public_id,
            'items' => [['inventoryEntryId' => $fixture['initiatorEntry']->public_id, 'quantity' => 1]],
        ]);
        $negotiation = Negotiation::query()->sole();
        $response->assertRedirect('/negotiations/'.$negotiation->public_id);
        self::assertNotSame($fixture['owner']->email, $negotiation->owner_alias);
        self::assertDatabaseHas('proposal_versions', ['negotiation_id' => $negotiation->id, 'version' => 1, 'status' => 'active']);

        $this->actingAs($fixture['stranger'])->get('/negotiations/'.$negotiation->public_id)->assertNotFound();
        $this->actingAs($fixture['owner'])->get('/negotiations/'.$negotiation->public_id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('trade')->where('section', 'negotiation')->where('reportingEnabled', false));
    }

    public function test_counter_replaces_one_side_and_stale_acceptance_is_rejected(): void
    {
        $fixture = $this->fixture();
        $negotiation = $this->start($fixture);
        $firstProposal = DB::table('proposal_versions')->where('negotiation_id', $negotiation->id)->where('version', 1)->first();

        $this->actingAs($fixture['owner'])->post('/negotiations/'.$negotiation->public_id.'/counter', [
            'items' => [['inventoryEntryId' => $fixture['ownerEntry']->public_id, 'quantity' => 1]],
        ])->assertRedirect();

        self::assertDatabaseHas('proposal_versions', ['negotiation_id' => $negotiation->id, 'version' => 1, 'status' => 'superseded']);
        $secondProposal = DB::table('proposal_versions')->where('negotiation_id', $negotiation->id)->where('version', 2)->first();
        self::assertNotNull($secondProposal);
        self::assertSame(2, DB::table('proposal_items')->where('proposal_version_id', $secondProposal->id)->count());

        $this->actingAs($fixture['initiator'])->post('/negotiations/'.$negotiation->public_id.'/accept', ['proposalId' => $firstProposal->public_id])->assertConflict();
        $this->actingAs($fixture['initiator'])->post('/negotiations/'.$negotiation->public_id.'/accept', ['proposalId' => $secondProposal->public_id])->assertRedirect();
        $this->actingAs($fixture['initiator'])->post('/negotiations/'.$negotiation->public_id.'/accept', ['proposalId' => $secondProposal->public_id])->assertRedirect();
        self::assertSame(1, DB::table('proposal_acceptances')->where('proposal_version_id', $secondProposal->id)->count());

        $this->actingAs($fixture['owner'])->post('/negotiations/'.$negotiation->public_id.'/accept', ['proposalId' => $secondProposal->public_id])->assertRedirect();
        self::assertSame('accepted', $negotiation->fresh()->status);
        self::assertDatabaseHas('proposal_versions', ['id' => $secondProposal->id, 'status' => 'accepted']);
        self::assertDatabaseHas('audit_events', ['actor_id' => $fixture['owner']->id, 'event_type' => 'negotiation.countered', 'subject_public_id' => $negotiation->public_id]);
        self::assertSame(2, DB::table('audit_events')->where('event_type', 'proposal.accepted')->where('subject_public_id', $negotiation->public_id)->count());
    }

    public function test_participants_can_reject_or_cancel_with_structured_reasons(): void
    {
        $fixture = $this->fixture();
        $rejected = $this->start($fixture);
        $this->actingAs($fixture['owner'])->post('/negotiations/'.$rejected->public_id.'/reject', ['reasonCode' => 'not_interested'])
            ->assertRedirect('/negotiations');
        self::assertSame('rejected', $rejected->fresh()->status);
        self::assertStringContainsString('not_interested', (string) DB::table('audit_events')->where('event_type', 'negotiation.rejected')->value('metadata'));

        $cancelled = $this->start($fixture);
        $this->actingAs($fixture['initiator'])->post('/negotiations/'.$cancelled->public_id.'/cancel', ['reasonCode' => 'cancelled_by_user'])
            ->assertRedirect('/negotiations');
        self::assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_inventory_and_listing_changes_invalidate_active_negotiations(): void
    {
        $fixture = $this->fixture();
        $negotiation = $this->start($fixture);

        $this->actingAs($fixture['initiator'])->patch('/inventory/'.$fixture['initiatorEntry']->public_id, ['quantity' => 2])->assertRedirect();
        self::assertSame('invalidated', $negotiation->fresh()->status);
        self::assertDatabaseHas('proposal_versions', ['negotiation_id' => $negotiation->id, 'status' => 'invalidated']);

        $second = $this->start($fixture);
        $this->actingAs($fixture['owner'])->delete('/listings/'.$fixture['listing']->public_id)->assertRedirect();
        self::assertSame('invalidated', $second->fresh()->status);
    }

    public function test_expiry_service_closes_stale_phase_three_records(): void
    {
        $fixture = $this->fixture();
        $negotiation = $this->start($fixture);
        $proposal = DB::table('proposal_versions')->where('negotiation_id', $negotiation->id)->first();
        DB::table('proposal_versions')->where('id', $proposal->id)->update(['expires_at' => now()->subMinute()]);
        $negotiation->update(['expires_at' => now()->subMinute()]);

        $preDealId = DB::table('pre_deal_versions')->insertGetId([
            'public_id' => (string) Str::uuid(), 'negotiation_id' => $negotiation->id, 'proposal_version_id' => $proposal->id,
            'proposer_id' => $fixture['initiator']->id, 'version' => 1, 'server_type' => 'public_server', 'status' => 'active',
            'fee_quote' => 0, 'expires_at' => now()->subMinute(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $dealId = DB::table('deals')->insertGetId([
            'public_id' => (string) Str::uuid(), 'negotiation_id' => $negotiation->id, 'proposal_version_id' => $proposal->id,
            'pre_deal_version_id' => $preDealId, 'status' => 'awaiting_final_acceptance', 'fee_quote' => 0,
            'expires_at' => now()->subMinute(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = (new PhaseTwoExpiryService)->expire();

        self::assertSame(1, $result['proposals']);
        self::assertSame(1, $result['negotiations']);
        self::assertSame('expired', $negotiation->fresh()->status);
        self::assertSame('active', DB::table('pre_deal_versions')->where('id', $preDealId)->value('status'));
        self::assertSame('awaiting_final_acceptance', DB::table('deals')->where('id', $dealId)->value('status'));
    }

    public function test_phase_three_expiry_is_registered_with_the_scheduler(): void
    {
        Artisan::call('schedule:list');

        self::assertStringContainsString('trade:expire-phase-three', Artisan::output());
    }

    private function start(array $fixture): Negotiation
    {
        $this->actingAs($fixture['initiator'])->post('/negotiations', [
            'listingId' => $fixture['listing']->public_id,
            'items' => [['inventoryEntryId' => $fixture['initiatorEntry']->public_id, 'quantity' => 1]],
        ])->assertRedirect();

        return Negotiation::query()->latest('id')->firstOrFail();
    }

    private function fixture(): array
    {
        $owner = $this->user('owner@example.test');
        $initiator = $this->user('initiator@example.test');
        $stranger = $this->user('stranger@example.test');
        foreach ([$owner, $initiator, $stranger] as $user) {
            DB::table('player_profiles')->insert(['user_id' => $user->id, 'usn' => 'USN-'.$user->id, 'ign' => 'IGN-'.$user->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        $categoryId = DB::table('catalog_categories')->insertGetId([
            'public_id' => (string) Str::uuid(), 'key' => 'boat', 'name' => 'Boats', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $ownerItem = CatalogItem::create(['category_id' => $categoryId, 'canonical_name' => 'Tidebreaker', 'aliases' => [], 'is_active' => true]);
        $initiatorItem = CatalogItem::create(['category_id' => $categoryId, 'canonical_name' => 'Storm Skiff', 'aliases' => [], 'is_active' => true]);
        $importId = DB::table('value_imports')->insertGetId([
            'public_id' => (string) Str::uuid(), 'source_key' => 'phase-three-test', 'status' => 'published', 'checksum' => hash('sha256', 'phase-three-test'),
            'source_timestamp' => now(), 'validation_errors' => json_encode([]), 'payload' => json_encode([]), 'imported_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $snapshotId = DB::table('value_snapshots')->insertGetId([
            'public_id' => (string) Str::uuid(), 'value_import_id' => $importId, 'version' => 'phase-three-test', 'checksum' => hash('sha256', 'phase-three-snapshot'),
            'source_timestamp' => now(), 'published_at' => now(), 'published_by' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('catalog_item_values')->insert([
            ['snapshot_id' => $snapshotId, 'catalog_item_id' => $ownerItem->id, 'unit_value' => 100, 'offer_range' => '95-105'],
            ['snapshot_id' => $snapshotId, 'catalog_item_id' => $initiatorItem->id, 'unit_value' => 100, 'offer_range' => '95-105'],
        ]);
        DB::table('system_settings')->insert(['key' => 'fair_threshold_percent', 'value' => json_encode(10), 'created_at' => now(), 'updated_at' => now()]);
        $ownerEntry = InventoryEntry::create(['user_id' => $owner->id, 'catalog_item_id' => $ownerItem->id, 'quantity' => 2]);
        $initiatorEntry = InventoryEntry::create(['user_id' => $initiator->id, 'catalog_item_id' => $initiatorItem->id, 'quantity' => 2]);
        $listing = Listing::create([
            'user_id' => $owner->id, 'value_snapshot_id' => $snapshotId, 'status' => 'active', 'current_version' => 1, 'published_at' => now(), 'expires_at' => now()->addDays(14),
        ]);
        $versionId = DB::table('listing_versions')->insertGetId([
            'listing_id' => $listing->id, 'version' => 1, 'value_snapshot_id' => $snapshotId,
            'offered_items' => json_encode([['inventory_entry_id' => $ownerEntry->public_id, 'catalog_item_id' => $ownerItem->id, 'quantity' => 1]]),
            'wanted_items' => json_encode([['catalog_item_id' => $initiatorItem->id, 'quantity' => 1]]),
            'offered_value' => 100, 'wanted_value' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('listing_version_items')->insert([
            ['listing_version_id' => $versionId, 'catalog_item_id' => $ownerItem->id, 'inventory_entry_id' => $ownerEntry->id, 'side' => 'offered', 'quantity' => 1, 'unit_value' => 100],
            ['listing_version_id' => $versionId, 'catalog_item_id' => $initiatorItem->id, 'inventory_entry_id' => null, 'side' => 'wanted', 'quantity' => 1, 'unit_value' => 100],
        ]);

        return compact('owner', 'initiator', 'stranger', 'ownerEntry', 'initiatorEntry', 'listing');
    }

    private function user(string $email): User
    {
        $user = User::create(['name' => Str::before($email, '@'), 'email' => $email, 'password' => 'password123', 'role' => 'player']);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
