<?php

namespace App\Domain\Trade;

use App\Models\Listing;
use App\Models\Negotiation;
use App\Models\ProposalVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class NegotiationService
{
    public function __construct(private readonly WflCalculator $calculator) {}

    public function start(User $actor, Listing $listing, array $initiatorItems, string $intent = 'offer'): Negotiation
    {
        return DB::transaction(function () use ($actor, $listing, $initiatorItems, $intent): Negotiation {
            $listing = Listing::whereKey($listing->id)->lockForUpdate()->firstOrFail();
            abort_if($listing->user_id === $actor->id, 422, 'You cannot negotiate with your own listing.');
            abort_unless($listing->status === 'active' && (! $listing->expires_at || $listing->expires_at->isFuture()), 409, 'Listing is no longer active.');
            $this->requireProfile($actor->id);
            $this->requireProfile($listing->user_id);

            $listingVersion = DB::table('listing_versions')->where('listing_id', $listing->id)->where('version', $listing->current_version)->first();
            abort_unless($listingVersion, 409, 'Listing version is unavailable.');
            $offered = is_string($listingVersion->offered_items) ? json_decode($listingVersion->offered_items, true, flags: JSON_THROW_ON_ERROR) : $listingVersion->offered_items;
            $ownerItems = collect($offered)->map(fn (array $item): array => ['inventoryEntryId' => $item['inventory_entry_id'], 'quantity' => $item['quantity']])->all();

            $negotiation = Negotiation::create([
                'listing_id' => $listing->id, 'initiator_id' => $actor->id, 'owner_id' => $listing->user_id,
                'value_snapshot_id' => $listing->value_snapshot_id, 'status' => 'proposed', 'active_proposal_version' => 1,
                'initiator_alias' => $this->alias(), 'owner_alias' => $this->alias(), 'last_activity_at' => now(),
                'expires_at' => now()->addDays((int) config('phase_two.negotiation_expiry_days', 7)),
            ]);
            $this->createProposal($negotiation, $actor->id, 1, $initiatorItems, $ownerItems, $intent);
            $this->audit($actor->id, 'negotiation.started', $negotiation->public_id, ['proposal_version' => 1]);
            if ($intent === 'request_more_items') {
                $this->audit($actor->id, 'proposal.adjustment_requested', $negotiation->public_id, ['proposal_version' => 1, 'request_code' => 'add_items']);
            }
            return $negotiation->fresh();
        });
    }

    public function counter(User $actor, Negotiation $negotiation, string $expectedProposalId, array $actorItems, string $intent = 'offer'): ProposalVersion
    {
        return DB::transaction(function () use ($actor, $negotiation, $expectedProposalId, $actorItems, $intent): ProposalVersion {
            $negotiation = Negotiation::whereKey($negotiation->id)->lockForUpdate()->firstOrFail();
            $this->guardAction($actor, $negotiation);
            abort_unless(in_array($negotiation->status, ['proposed', 'countered'], true), 409, 'Negotiation does not accept counteroffers.');
            $active = ProposalVersion::where('negotiation_id', $negotiation->id)
                ->where('version', $negotiation->active_proposal_version)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($active->public_id, $expectedProposalId), 409, 'Proposal version is stale.');
            abort_unless($active->status === 'active' && $active->expires_at->isFuture(), 409, 'Proposal is not active.');
            $current = DB::table('proposal_items')->join('inventory_entries', 'inventory_entries.id', '=', 'proposal_items.inventory_entry_id')
                ->where('proposal_version_id', $active->id)->get(['proposal_items.side', 'inventory_entries.public_id as inventoryEntryId', 'proposal_items.quantity'])
                ->groupBy('side')->map(fn ($items): array => $items->map(fn ($item): array => ['inventoryEntryId' => $item->inventoryEntryId, 'quantity' => $item->quantity])->all());
            $initiatorItems = $actor->id === $negotiation->initiator_id ? $actorItems : $current->get('initiator', []);
            $ownerItems = $actor->id === $negotiation->owner_id ? $actorItems : $current->get('owner', []);
            ProposalVersion::where('negotiation_id', $negotiation->id)->where('status', 'active')->update(['status' => 'superseded']);
            $next = ((int) $negotiation->active_proposal_version) + 1;
            $proposal = $this->createProposal($negotiation, $actor->id, $next, $initiatorItems, $ownerItems, $intent);
            $negotiation->update(['status' => 'countered', 'active_proposal_version' => $next, 'last_activity_at' => now(), 'expires_at' => now()->addDays((int) config('phase_two.negotiation_expiry_days', 7))]);
            $this->audit($actor->id, 'negotiation.countered', $negotiation->public_id, ['proposal_version' => $next]);
            if ($intent === 'request_more_items') {
                $this->audit($actor->id, 'proposal.adjustment_requested', $negotiation->public_id, ['proposal_version' => $next, 'request_code' => 'add_items']);
            }
            return $proposal;
        });
    }

    public function accept(User $actor, Negotiation $negotiation, int $proposalVersion): ProposalVersion
    {
        return DB::transaction(function () use ($actor, $negotiation, $proposalVersion): ProposalVersion {
            $negotiation = Negotiation::whereKey($negotiation->id)->lockForUpdate()->firstOrFail();
            $this->guardAction($actor, $negotiation);
            abort_unless((int) $negotiation->active_proposal_version === $proposalVersion, 409, 'Proposal version is stale.');
            $proposal = ProposalVersion::where('negotiation_id', $negotiation->id)->where('version', $proposalVersion)->lockForUpdate()->firstOrFail();
            abort_unless($proposal->status === 'active' && $proposal->expires_at->isFuture(), 409, 'Proposal is not active.');
            abort_unless($proposal->intent === 'offer', 409, 'Adjustment requests cannot be accepted. Send a counteroffer or reject the request.');
            $this->validateProposalInventory($proposal);
            $inserted = DB::table('proposal_acceptances')->insertOrIgnore(['proposal_version_id' => $proposal->id, 'user_id' => $actor->id, 'accepted_at' => now()]);
            if (DB::table('proposal_acceptances')->where('proposal_version_id', $proposal->id)->count() === 2) {
                $proposal->update(['status' => 'accepted']);
                $negotiation->update(['status' => 'accepted', 'last_activity_at' => now()]);
            }
            if ($inserted === 1) {
                $this->audit($actor->id, 'proposal.accepted', $negotiation->public_id, ['proposal_version' => $proposalVersion]);
            }
            return $proposal->fresh();
        });
    }

    public function reject(User $actor, Negotiation $negotiation, string $reasonCode): void
    {
        $this->finish($actor, $negotiation, 'rejected', $reasonCode);
    }

    public function cancel(User $actor, Negotiation $negotiation, string $reasonCode): void
    {
        $this->finish($actor, $negotiation, 'cancelled', $reasonCode);
    }

    public function validateProposalInventory(ProposalVersion $proposal): void
    {
        $invalid = DB::table('proposal_items')->join('inventory_entries', 'inventory_entries.id', '=', 'proposal_items.inventory_entry_id')
            ->where('proposal_items.proposal_version_id', $proposal->id)
            ->where(fn ($query) => $query->whereNotNull('inventory_entries.archived_at')->orWhereColumn('proposal_items.quantity', '>', 'inventory_entries.quantity'))
            ->exists();
        abort_if($invalid, 409, 'Proposal inventory is no longer available.');
    }

    private function createProposal(Negotiation $negotiation, int $proposerId, int $version, array $initiatorItems, array $ownerItems, string $intent): ProposalVersion
    {
        abort_unless(in_array($intent, ['offer', 'request_more_items'], true), 422, 'Proposal intent is invalid.');
        $initiator = $this->validatedItems($negotiation->initiator_id, $initiatorItems, 'initiator', $negotiation->value_snapshot_id);
        $owner = $this->validatedItems($negotiation->owner_id, $ownerItems, 'owner', $negotiation->value_snapshot_id);
        $initiatorValue = $this->total($initiator);
        $ownerValue = $this->total($owner);
        if ($intent === 'request_more_items') {
            $actorGives = $proposerId === $negotiation->initiator_id ? $initiator : $owner;
            $actorReceives = $proposerId === $negotiation->initiator_id ? $owner : $initiator;
            $result = $this->calculator->calculate(
                $this->calculatorItems($actorGives),
                $this->calculatorItems($actorReceives),
                $this->threshold(),
                (string) (DB::table('value_snapshots')->where('id', $negotiation->value_snapshot_id)->value('version') ?? ''),
            );
            abort_unless($result->status === WflStatus::Lose, 422, 'More items can only be requested when the proposal is Lose.');
        }
        $proposal = ProposalVersion::create([
            'negotiation_id' => $negotiation->id, 'proposer_id' => $proposerId, 'value_snapshot_id' => $negotiation->value_snapshot_id,
            'version' => $version, 'status' => 'active', 'intent' => $intent, 'initiator_value' => $initiatorValue, 'owner_value' => $ownerValue,
            'expires_at' => now()->addHours((int) config('phase_two.proposal_expiry_hours', 24)),
        ]);
        $proposal->items()->createMany([...$initiator, ...$owner]);
        return $proposal;
    }

    private function validatedItems(int $userId, array $items, string $side, ?int $snapshotId): array
    {
        abort_if($items === [], 422, 'Each proposal side requires at least one inventory item.');
        $seen = [];
        return collect($items)->map(function (array $item) use ($userId, $side, $snapshotId, &$seen): array {
            $quantity = (int) ($item['quantity'] ?? 0);
            abort_if($quantity < 1, 422, 'Proposal quantity must be positive.');
            $entry = DB::table('inventory_entries')->where('public_id', (string) ($item['inventoryEntryId'] ?? ''))->where('user_id', $userId)->whereNull('archived_at')->first();
            abort_unless($entry, 422, 'Proposal item is not available to its owner.');
            abort_if(isset($seen[$entry->id]), 422, 'Proposal item is duplicated.');
            abort_if($quantity > $entry->quantity, 422, 'Proposal quantity exceeds inventory.');
            $seen[$entry->id] = true;
            $unit = $snapshotId ? DB::table('catalog_item_values')->where('snapshot_id', $snapshotId)->where('catalog_item_id', $entry->catalog_item_id)->value('unit_value') : null;
            return ['owner_user_id' => $userId, 'inventory_entry_id' => $entry->id, 'catalog_item_id' => $entry->catalog_item_id, 'side' => $side, 'quantity' => $quantity, 'unit_value' => $unit];
        })->all();
    }

    private function total(array $items): ?float
    {
        return collect($items)->contains(fn (array $item): bool => $item['unit_value'] === null) ? null : (float) collect($items)->sum(fn (array $item): float => (float) $item['unit_value'] * $item['quantity']);
    }

    private function calculatorItems(array $items): array
    {
        return collect($items)->map(fn (array $item): array => [
            'id' => (string) $item['inventory_entry_id'],
            'quantity' => $item['quantity'],
            'unit_value' => $item['unit_value'],
        ])->all();
    }

    private function threshold(): float
    {
        $stored = DB::table('system_settings')->where('key', 'fair_threshold_percent')->value('value');

        return $stored === null ? 10.0 : (float) (json_decode((string) $stored, true) ?? 10);
    }

    private function finish(User $actor, Negotiation $negotiation, string $status, string $reasonCode): void
    {
        DB::transaction(function () use ($actor, $negotiation, $status, $reasonCode): void {
            $negotiation = Negotiation::whereKey($negotiation->id)->lockForUpdate()->firstOrFail();
            $this->guardAction($actor, $negotiation);
            abort_unless(in_array($negotiation->status, ['proposed', 'countered'], true), 409, 'Negotiation is already closed.');
            $negotiation->update(['status' => $status, 'last_activity_at' => now()]);
            ProposalVersion::where('negotiation_id', $negotiation->id)->where('status', 'active')->update(['status' => $status]);
            $this->audit($actor->id, 'negotiation.'.$status, $negotiation->public_id, ['reason_code' => $reasonCode, 'proposal_version' => $negotiation->active_proposal_version]);
        });
    }

    private function guardAction(User $actor, Negotiation $negotiation): void
    {
        abort_unless($negotiation->isParticipant($actor->id), 404);
        abort_if($negotiation->expires_at->isPast(), 409, 'Negotiation has expired.');
    }

    private function requireProfile(int $userId): void
    {
        abort_unless(DB::table('player_profiles')->where('user_id', $userId)->whereNotNull('usn')->whereNotNull('ign')->exists(), 422, 'A complete player profile is required.');
    }

    private function alias(): string
    {
        return 'Trader-'.Str::upper(Str::random(8));
    }

    private function audit(?int $actorId, string $event, string $subject, array $metadata): void
    {
        DB::table('audit_events')->insert(['public_id' => (string) Str::uuid(), 'actor_id' => $actorId, 'event_type' => $event, 'subject_type' => 'negotiation', 'subject_public_id' => $subject, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
