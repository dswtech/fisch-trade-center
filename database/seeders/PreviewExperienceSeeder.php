<?php

namespace Database\Seeders;

use App\Models\CatalogItem;
use App\Models\InventoryEntry;
use App\Models\Listing;
use App\Models\ListingVersion;
use App\Models\Negotiation;
use App\Models\ProposalVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class PreviewExperienceSeeder extends Seeder
{
    public function run(): void
    {
        $this->guardLocalDatabase();
        $this->call(PreviewAccountSeeder::class);

        DB::transaction(function (): void {
            $snapshot = DB::table('value_snapshots')->whereNotNull('published_at')->latest('published_at')->first();

            if (! $snapshot) {
                throw new RuntimeException('Preview experience requires a published value snapshot.');
            }

            $items = $this->valuedItems((int) $snapshot->id);

            if ($items->count() < 2) {
                throw new RuntimeException('Preview experience requires at least two active catalog items with values.');
            }

            while ($items->count() < 12) {
                $items = $items->concat($items)->take(12)->values();
            }

            $player = User::where('email', 'user@ftc.test')->firstOrFail();
            $this->profile($player, 'PreviewPlayer', 'FTC Preview');
            $playerEntries = $this->inventory($player, $items->take(12), 2);

            $owners = collect([
                ['name' => 'Demo Angler Coral', 'email' => 'demo-coral@ftc.test', 'usn' => 'DemoCoral', 'ign' => 'Coral Current'],
                ['name' => 'Demo Angler Tide', 'email' => 'demo-tide@ftc.test', 'usn' => 'DemoTide', 'ign' => 'Tide Keeper'],
                ['name' => 'Demo Angler Abyss', 'email' => 'demo-abyss@ftc.test', 'usn' => 'DemoAbyss', 'ign' => 'Abyss Scout'],
                ['name' => 'Demo Angler Reef', 'email' => 'demo-reef@ftc.test', 'usn' => 'DemoReef', 'ign' => 'Reef Runner'],
                ['name' => 'Demo Angler Pearl', 'email' => 'demo-pearl@ftc.test', 'usn' => 'DemoPearl', 'ign' => 'Pearl Hunter'],
            ])->map(function (array $fixture) use ($items): array {
                $user = User::updateOrCreate(
                    ['email' => $fixture['email']],
                    ['public_id' => $this->uuid('user/'.$fixture['email']), 'name' => $fixture['name'], 'password' => 'Preview123!', 'role' => 'player', 'email_verified_at' => now()],
                );
                $this->profile($user, $fixture['usn'], $fixture['ign']);

                return ['user' => $user, 'entries' => $this->inventory($user, $items, 6)];
            });

            $listings = collect();
            foreach (range(0, 17) as $index) {
                $owner = $owners[$index % $owners->count()];
                $offered = collect([$items[$index % $items->count()], $items[($index + 3) % $items->count()]])
                    ->take(($index % 3) + 1);
                $wanted = collect([$items[($index + 7) % $items->count()], $items[($index + 11) % $items->count()]])
                    ->take(($index % 2) + 1);
                $listings->push($this->listing(
                    key: 'market-'.$index,
                    owner: $owner['user'],
                    ownerEntries: $owner['entries'],
                    snapshotId: (int) $snapshot->id,
                    offered: $offered,
                    wanted: $wanted,
                    ageMinutes: ($index + 1) * 11,
                ));
            }

            $this->listing(
                key: 'player-offer',
                owner: $player,
                ownerEntries: $playerEntries,
                snapshotId: (int) $snapshot->id,
                offered: $items->slice(1, 2),
                wanted: $items->slice(8, 2),
                ageMinutes: 8,
            );

            $this->negotiation($player, $owners->first()['user'], $listings->first(), $playerEntries, $owners->first()['entries'], (int) $snapshot->id, $items);

            $owners->each(fn (array $owner) => InventoryEntry::where('user_id', $owner['user']->id)
                ->whereNotIn('catalog_item_id', $items->pluck('id'))->delete());
        });
    }

    private function guardLocalDatabase(): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $host = config("database.connections.{$connection}.host");

        if (app()->environment('production') || ($driver !== 'sqlite' && ! in_array($host, ['127.0.0.1', 'localhost'], true))) {
            throw new RuntimeException('Preview experience may only be seeded into a local or disposable database.');
        }
    }

    /** @return Collection<int, object> */
    private function valuedItems(int $snapshotId): Collection
    {
        return DB::table('catalog_items as ci')
            ->join('catalog_item_values as civ', fn ($join) => $join->on('civ.catalog_item_id', '=', 'ci.id')->where('civ.snapshot_id', $snapshotId))
            ->join('catalog_categories as cc', 'cc.id', '=', 'ci.category_id')
            ->where('ci.is_active', true)->where('cc.is_active', true)
            ->orderBy('cc.key')->orderBy('ci.canonical_name')
            ->limit(32)
            ->get(['ci.id', 'ci.public_id', 'ci.canonical_name', 'civ.unit_value']);
    }

    /** @param Collection<int, object> $items @return Collection<int, InventoryEntry> */
    private function inventory(User $user, Collection $items, int $baseQuantity): Collection
    {
        return $items->values()->mapWithKeys(function (object $item, int $index) use ($user, $baseQuantity): array {
            $entry = InventoryEntry::updateOrCreate(
                ['user_id' => $user->id, 'catalog_item_id' => $item->id],
                ['public_id' => $this->uuid('inventory/'.$user->email.'/'.$item->id), 'quantity' => $baseQuantity + ($index % 4), 'archived_at' => null],
            );

            return [$item->id => $entry];
        });
    }

    private function profile(User $user, string $usn, string $ign): void
    {
        DB::table('player_profiles')->updateOrInsert(
            ['user_id' => $user->id],
            ['usn' => $usn, 'ign' => $ign, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    /** @param Collection<int, InventoryEntry> $ownerEntries @param Collection<int, object> $offered @param Collection<int, object> $wanted */
    private function listing(string $key, User $owner, Collection $ownerEntries, int $snapshotId, Collection $offered, Collection $wanted, int $ageMinutes): Listing
    {
        $publishedAt = now()->subMinutes($ageMinutes);
        $listing = Listing::updateOrCreate(
            ['public_id' => $this->uuid('listing/'.$key)],
            ['user_id' => $owner->id, 'value_snapshot_id' => $snapshotId, 'status' => 'active', 'current_version' => 1, 'published_at' => $publishedAt, 'expires_at' => $publishedAt->copy()->addDays(14)],
        );
        $offeredRows = $offered->values()->map(fn (object $item, int $index): array => [
            'inventory_entry_id' => $ownerEntries[$item->id]->public_id,
            'catalog_item_id' => $item->id,
            'quantity' => ($index % 2) + 1,
        ]);
        $wantedRows = $wanted->values()->map(fn (object $item, int $index): array => [
            'catalog_item_id' => $item->id,
            'quantity' => ($index % 2) + 1,
        ]);
        $offeredValue = $offeredRows->sum(fn (array $row): float => (float) $offered->firstWhere('id', $row['catalog_item_id'])->unit_value * $row['quantity']);
        $wantedValue = $wantedRows->sum(fn (array $row): float => (float) $wanted->firstWhere('id', $row['catalog_item_id'])->unit_value * $row['quantity']);
        $version = ListingVersion::updateOrCreate(
            ['listing_id' => $listing->id, 'version' => 1],
            ['value_snapshot_id' => $snapshotId, 'offered_items' => $offeredRows->all(), 'wanted_items' => $wantedRows->all(), 'offered_value' => $offeredValue, 'wanted_value' => $wantedValue],
        );
        $version->items()->delete();
        $version->items()->createMany([
            ...$offeredRows->map(fn (array $row): array => [...$row, 'inventory_entry_id' => $ownerEntries[$row['catalog_item_id']]->id, 'side' => 'offered', 'unit_value' => $offered->firstWhere('id', $row['catalog_item_id'])->unit_value])->all(),
            ...$wantedRows->map(fn (array $row): array => [...$row, 'side' => 'wanted', 'unit_value' => $wanted->firstWhere('id', $row['catalog_item_id'])->unit_value])->all(),
        ]);

        return $listing;
    }

    /** @param Collection<int, InventoryEntry> $playerEntries @param Collection<int, InventoryEntry> $ownerEntries @param Collection<int, object> $items */
    private function negotiation(User $player, User $owner, Listing $listing, Collection $playerEntries, Collection $ownerEntries, int $snapshotId, Collection $items): void
    {
        $negotiation = Negotiation::updateOrCreate(
            ['public_id' => $this->uuid('negotiation/player-preview')],
            ['listing_id' => $listing->id, 'initiator_id' => $player->id, 'owner_id' => $owner->id, 'value_snapshot_id' => $snapshotId, 'status' => 'proposed', 'active_proposal_version' => 1, 'initiator_alias' => 'Blue Angler', 'owner_alias' => 'Coral Trader', 'last_activity_at' => now()->subMinutes(4), 'expires_at' => now()->addHours(20)],
        );
        $proposal = ProposalVersion::updateOrCreate(
            ['negotiation_id' => $negotiation->id, 'version' => 1],
            ['public_id' => $this->uuid('proposal/player-preview/1'), 'proposer_id' => $player->id, 'value_snapshot_id' => $snapshotId, 'status' => 'active', 'initiator_value' => $items[0]->unit_value, 'owner_value' => $items[7]->unit_value, 'expires_at' => now()->addHours(20)],
        );
        $proposal->items()->delete();
        $proposal->items()->createMany([
            ['owner_user_id' => $player->id, 'inventory_entry_id' => $playerEntries[$items[0]->id]->id, 'catalog_item_id' => $items[0]->id, 'side' => 'initiator', 'quantity' => 1, 'unit_value' => $items[0]->unit_value],
            ['owner_user_id' => $owner->id, 'inventory_entry_id' => $ownerEntries[$items[7]->id]->id, 'catalog_item_id' => $items[7]->id, 'side' => 'owner', 'quantity' => 1, 'unit_value' => $items[7]->unit_value],
        ]);
        DB::table('proposal_acceptances')->where('proposal_version_id', $proposal->id)->delete();
    }

    private function uuid(string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'fisch-trade-center/preview/'.$key)->toString();
    }
}
