<?php

namespace App\Domain\Trade;

use App\Models\Deal;
use App\Models\DealIdentitySnapshot;
use App\Models\Listing;
use App\Models\Negotiation;
use App\Models\PreDealVersion;
use App\Models\ProposalVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DealService
{
    public function __construct(private readonly NegotiationService $negotiations) {}

    public function proposeServer(User $actor, Negotiation $negotiation, string $serverType): PreDealVersion
    {
        abort_unless(in_array($serverType, ['public_server', 'private_server'], true), 422, 'Invalid server type.');
        return DB::transaction(function () use ($actor, $negotiation, $serverType): PreDealVersion {
            $negotiation = Negotiation::whereKey($negotiation->id)->lockForUpdate()->firstOrFail();
            $this->guardParticipant($actor, $negotiation);
            abort_unless($negotiation->status === 'accepted', 409, 'Proposal must be accepted first.');
            abort_if(Deal::query()->where('negotiation_id', $negotiation->id)->exists(), 409, 'A deal already exists for this negotiation.');
            $proposal = ProposalVersion::where('negotiation_id', $negotiation->id)->where('version', $negotiation->active_proposal_version)->where('status', 'accepted')->firstOrFail();
            PreDealVersion::where('negotiation_id', $negotiation->id)->where('status', 'active')->update(['status' => 'superseded']);
            $next = ((int) PreDealVersion::where('negotiation_id', $negotiation->id)->max('version')) + 1;
            $preDeal = PreDealVersion::create([
                'negotiation_id' => $negotiation->id, 'proposal_version_id' => $proposal->id, 'proposer_id' => $actor->id,
                'version' => $next, 'server_type' => $serverType, 'status' => 'active', 'fee_quote' => 0,
                'expires_at' => now()->addMinutes((int) config('phase_two.pre_deal_expiry_minutes', 30)),
            ]);
            DB::table('pre_deal_acceptances')->insert(['pre_deal_version_id' => $preDeal->id, 'user_id' => $actor->id, 'accepted_at' => now()]);
            $this->audit($actor->id, 'pre_deal.proposed', $negotiation->public_id, ['pre_deal_version' => $next, 'server_type' => $serverType]);
            return $preDeal;
        });
    }

    public function acceptServer(User $actor, PreDealVersion $preDeal): Deal
    {
        return DB::transaction(function () use ($actor, $preDeal): Deal {
            $preDeal = PreDealVersion::whereKey($preDeal->id)->lockForUpdate()->firstOrFail();
            $negotiation = Negotiation::whereKey($preDeal->negotiation_id)->lockForUpdate()->firstOrFail();
            $this->guardParticipant($actor, $negotiation);
            abort_unless($preDeal->status === 'active' && $preDeal->expires_at->isFuture(), 409, 'Pre-deal version is not active.');
            $latest = (int) PreDealVersion::where('negotiation_id', $negotiation->id)->max('version');
            abort_unless($preDeal->version === $latest, 409, 'Pre-deal version is stale.');
            DB::table('pre_deal_acceptances')->insertOrIgnore(['pre_deal_version_id' => $preDeal->id, 'user_id' => $actor->id, 'accepted_at' => now()]);
            if (DB::table('pre_deal_acceptances')->where('pre_deal_version_id', $preDeal->id)->count() === 2) {
                $preDeal->update(['status' => 'accepted']);
                return Deal::firstOrCreate(['negotiation_id' => $negotiation->id], [
                    'proposal_version_id' => $preDeal->proposal_version_id, 'pre_deal_version_id' => $preDeal->id,
                    'status' => 'awaiting_final_acceptance', 'fee_quote' => 0, 'expires_at' => $preDeal->expires_at,
                ]);
            }
            return Deal::firstOrNew(['negotiation_id' => $negotiation->id], [
                'proposal_version_id' => $preDeal->proposal_version_id, 'pre_deal_version_id' => $preDeal->id,
                'status' => 'pre_deal', 'fee_quote' => 0, 'expires_at' => $preDeal->expires_at,
            ]);
        });
    }

    public function finalAccept(User $actor, Deal $deal, bool $disclaimerAccepted): Deal
    {
        abort_unless($disclaimerAccepted, 422, 'The non-escrow disclaimer must be accepted.');
        return DB::transaction(function () use ($actor, $deal): Deal {
            $deal = Deal::whereKey($deal->id)->lockForUpdate()->firstOrFail();
            $negotiation = Negotiation::whereKey($deal->negotiation_id)->lockForUpdate()->firstOrFail();
            $this->guardParticipant($actor, $negotiation);
            if ($deal->status === 'revealed') {
                return $deal;
            }
            abort_unless($deal->status === 'awaiting_final_acceptance' && $deal->expires_at->isFuture(), 409, 'Deal is not available for final acceptance.');
            abort_unless($negotiation->status === 'accepted', 409, 'Negotiation is no longer accepted.');
            abort_if(config('phase_two.wallet_enabled') || config('phase_two.fee_enabled') || (float) $deal->fee_quote !== 0.0, 503, 'Wallet and fee settlement are not configured.');
            $proposal = ProposalVersion::whereKey($deal->proposal_version_id)->lockForUpdate()->firstOrFail();
            $preDeal = PreDealVersion::whereKey($deal->pre_deal_version_id)->lockForUpdate()->firstOrFail();
            abort_unless($proposal->status === 'accepted' && $preDeal->status === 'accepted', 409, 'Deal binding is stale.');
            abort_unless($proposal->negotiation_id === $negotiation->id && $preDeal->proposal_version_id === $proposal->id, 409, 'Deal binding is invalid.');
            $listing = Listing::query()->whereKey($negotiation->listing_id)->lockForUpdate()->firstOrFail();
            abort_unless($listing->status === 'active' && (! $listing->expires_at || $listing->expires_at->isFuture()), 409, 'Listing is no longer active.');
            $this->negotiations->validateProposalInventory($proposal);
            $bindingHash = hash('sha256', implode('|', [$proposal->id, $preDeal->id, '0.00', $deal->expires_at->toIso8601String()]));
            DB::table('deal_acceptances')->insertOrIgnore(['deal_id' => $deal->id, 'user_id' => $actor->id, 'binding_hash' => $bindingHash, 'accepted_at' => now()]);
            if (DB::table('deal_acceptances')->where('deal_id', $deal->id)->count() < 2) {
                $this->audit($actor->id, 'deal.final_accepted', $deal->public_id, ['completed' => false]);
                return $deal->fresh();
            }

            $profiles = DB::table('player_profiles')->whereIn('user_id', [$negotiation->initiator_id, $negotiation->owner_id])->lockForUpdate()->get()->keyBy('user_id');
            abort_unless($profiles->count() === 2, 409, 'Both player profiles are required.');
            $deal->update(['status' => 'finalizing']);
            foreach ([$negotiation->initiator_id, $negotiation->owner_id] as $userId) {
                $profile = $profiles[$userId];
                DealIdentitySnapshot::firstOrCreate(['deal_id' => $deal->id, 'user_id' => $userId], ['usn' => $profile->usn, 'ign' => $profile->ign]);
            }
            $revealExpiry = now()->addDays((int) config('phase_two.reveal_expiry_days', 30));
            DB::table('reveal_entitlements')->insertOrIgnore([
                ['deal_id' => $deal->id, 'viewer_user_id' => $negotiation->initiator_id, 'subject_user_id' => $negotiation->owner_id, 'expires_at' => $revealExpiry],
                ['deal_id' => $deal->id, 'viewer_user_id' => $negotiation->owner_id, 'subject_user_id' => $negotiation->initiator_id, 'expires_at' => $revealExpiry],
            ]);
            $deal->update(['status' => 'revealed', 'snapshot' => $this->safeSnapshot($proposal, $preDeal), 'revealed_at' => now()]);
            DB::table('listings')->where('id', $negotiation->listing_id)->where('status', 'active')->update(['status' => 'matched', 'updated_at' => now()]);
            $this->audit($actor->id, 'deal.revealed', $deal->public_id, ['fee_quote' => 0, 'proposal_version' => $proposal->version, 'pre_deal_version' => $preDeal->version]);
            return $deal->fresh();
        }, 3);
    }

    public function view(User $actor, Deal $deal): array
    {
        $negotiation = Negotiation::findOrFail($deal->negotiation_id);
        $this->guardParticipant($actor, $negotiation);
        $identity = null;
        $entitlement = DB::table('reveal_entitlements')->where('deal_id', $deal->id)->where('viewer_user_id', $actor->id)->where('expires_at', '>', now())->first();
        if ($deal->status === 'revealed' && $entitlement) {
            $snapshot = DealIdentitySnapshot::where('deal_id', $deal->id)->where('user_id', $entitlement->subject_user_id)->firstOrFail();
            $identity = ['usn' => $snapshot->usn, 'ign' => $snapshot->ign, 'unverified' => true, 'availableUntil' => $entitlement->expires_at];
        }
        return ['id' => $deal->public_id, 'negotiationId' => $negotiation->public_id, 'status' => $deal->status, 'feeQuote' => (float) $deal->fee_quote, 'expiresAt' => $deal->expires_at, 'snapshot' => $deal->snapshot, 'counterpartyIdentity' => $identity];
    }

    private function safeSnapshot(ProposalVersion $proposal, PreDealVersion $preDeal): array
    {
        $items = DB::table('proposal_items')->join('catalog_items', 'catalog_items.id', '=', 'proposal_items.catalog_item_id')
            ->where('proposal_items.proposal_version_id', $proposal->id)->orderBy('proposal_items.id')
            ->get(['catalog_items.public_id as catalogItemId', 'catalog_items.canonical_name as name', 'proposal_items.side', 'proposal_items.quantity', 'proposal_items.unit_value as unitValue'])
            ->map(fn ($row): array => (array) $row)->all();
        return [
            'proposalVersion' => $proposal->version,
            'valueSnapshotVersion' => DB::table('value_snapshots')->where('id', $proposal->value_snapshot_id)->value('version'),
            'serverChoiceVersion' => $preDeal->version,
            'serverType' => $preDeal->server_type,
            'feeQuote' => 0,
            'items' => $items,
        ];
    }

    private function guardParticipant(User $actor, Negotiation $negotiation): void
    {
        abort_unless($negotiation->isParticipant($actor->id), 404);
    }

    private function audit(?int $actorId, string $event, string $subject, array $metadata): void
    {
        DB::table('audit_events')->insert(['public_id' => (string) Str::uuid(), 'actor_id' => $actorId, 'event_type' => $event, 'subject_type' => 'deal', 'subject_public_id' => $subject, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
