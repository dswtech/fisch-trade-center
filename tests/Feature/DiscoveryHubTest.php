<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Listing;
use App\Models\ListingVersion;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DiscoveryHubTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);
    }

    public function test_guest_receives_global_market_insights_without_private_recommendations(): void
    {
        $this->get('/')->assertOk()->assertDontSee('SeedSecretUSN')->assertDontSee('SeedSecretIGN')->assertInertia(fn (Assert $page) => $page
            ->component('discovery-hub')
            ->where('marketInsights.activeListingCount', 26)
            ->where('marketInsights.mostOffered.0.name', 'Abyssal Current')
            ->where('marketInsights.mostOffered.0.listingCount', 26)
            ->where('marketInsights.mostWanted.0.name', 'Tidebreaker')
            ->where('personalRecommendations.state', 'guest')
            ->has('personalRecommendations.results', 0));
    }

    public function test_player_recommendations_are_batched_and_use_available_inventory_quantity(): void
    {
        $player = User::where('email', 'e2e@example.test')->firstOrFail();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($player)->get('/');
        $queryCount = count(DB::getQueryLog());

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('personalRecommendations.state', 'ready')
            ->has('personalRecommendations.results', 3)
            ->where('personalRecommendations.results.0.status', 'fair')
            ->where('personalRecommendations.results.0.items.0.name', 'Abyssal Current')
            ->where('personalRecommendations.results.0.items.0.quantity', 1));
        self::assertLessThanOrEqual(20, $queryCount, 'Discovery recommendations must not query once per listing.');
    }

    public function test_player_without_inventory_receives_an_explicit_empty_state(): void
    {
        $player = User::create(['name' => 'Empty Player', 'email' => 'empty-discovery@example.test', 'password' => 'password123']);
        $player->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($player)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('personalRecommendations.state', 'empty_inventory')
            ->has('personalRecommendations.results', 0));
    }

    public function test_market_ranking_counts_each_listing_once_and_only_uses_current_version(): void
    {
        $listing = Listing::orderBy('id')->firstOrFail();
        $oldVersion = $listing->latestVersion;
        $offered = CatalogItem::where('canonical_name', 'Abyssal Current')->firstOrFail();
        $replacement = CatalogItem::where('canonical_name', 'Golden Bobber')->firstOrFail();

        DB::table('listing_version_items')->insert([
            'listing_version_id' => $oldVersion->id,
            'catalog_item_id' => $offered->id,
            'inventory_entry_id' => $oldVersion->items()->where('side', 'offered')->value('inventory_entry_id'),
            'side' => 'offered',
            'quantity' => 1,
            'unit_value' => 2400,
        ]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('marketInsights.mostOffered.0.listingCount', 26));

        $version = ListingVersion::create([
            'listing_id' => $listing->id,
            'version' => 2,
            'value_snapshot_id' => $oldVersion->value_snapshot_id,
            'offered_items' => [],
            'wanted_items' => [],
            'offered_value' => null,
            'wanted_value' => null,
        ]);
        $version->items()->create([
            'catalog_item_id' => $replacement->id,
            'side' => 'offered',
            'quantity' => 1,
            'unit_value' => null,
        ]);
        $listing->update(['current_version' => 2]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('marketInsights.mostOffered.0.name', 'Abyssal Current')
            ->where('marketInsights.mostOffered.0.listingCount', 25));
    }
}
