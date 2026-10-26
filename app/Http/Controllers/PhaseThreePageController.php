<?php

namespace App\Http\Controllers;

use App\Domain\Trade\RecommendationService;
use App\Domain\Trade\WflCalculator;
use App\Models\Listing;
use App\Models\Negotiation;
use App\Models\ProposalVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class PhaseThreePageController extends Controller
{
    public function recommendations(Request $request, string $listing, RecommendationService $recommendations): Response
    {
        $listing = Listing::where('public_id', $listing)->firstOrFail();
        abort_if($listing->user_id === $request->user()->id, 422, 'You cannot negotiate with your own listing.');
        $result = $recommendations->recommend($request->user()->id, $listing);
        $version = DB::table('listing_versions')->where('listing_id', $listing->id)->where('version', $listing->current_version)->firstOrFail();

        return Inertia::render('trade', [
            'section' => 'recommendations',
            'listing' => [
                'id' => $listing->public_id,
                'alias' => 'Anonymous trader',
                'offered' => $this->listingSide($version->id, 'offered'),
                'wanted' => $this->listingSide($version->id, 'wanted'),
                'snapshotVersion' => $result['snapshotId'] ? (DB::table('value_snapshots')->where('public_id', $result['snapshotId'])->value('version') ?? '—') : '—',
            ],
            'missingItems' => $result['missingItems'],
            'recommendations' => collect($result['candidates'])->map(fn (array $candidate, int $index): array => [
                'id' => 'candidate-'.($index + 1),
                'items' => collect($candidate['items'])->map(fn (array $item): array => [
                    'id' => $item['inventoryEntryId'],
                    'inventoryEntryId' => $item['inventoryEntryId'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unitValue' => $item['unitValue'],
                    'offerRange' => $item['offerRange'],
                ])->all(),
                'giveTotal' => $candidate['total'],
                'receiveTotal' => $candidate['receiveTotal'],
                'delta' => $candidate['delta'],
                'deltaPercent' => $candidate['deltaPercent'],
                'status' => $candidate['status'],
                'perspective' => $candidate['perspective'],
                'reasons' => [],
            ])->all(),
        ]);
    }

    public function index(Request $request): Response
    {
        $rows = Negotiation::where(fn ($query) => $query->where('initiator_id', $request->user()->id)->orWhere('owner_id', $request->user()->id))
            ->orderByDesc('last_activity_at')->get()
            ->map(fn (Negotiation $negotiation): array => $this->negotiation($negotiation, $request->user()->id));

        return Inertia::render('trade', ['section' => 'negotiations', 'negotiations' => $rows]);
    }

    public function show(Request $request, string $negotiation, WflCalculator $calculator): Response
    {
        $negotiation = Negotiation::where('public_id', $negotiation)->firstOrFail();
        abort_unless($negotiation->isParticipant($request->user()->id), 404);
        $proposals = ProposalVersion::where('negotiation_id', $negotiation->id)->orderByDesc('version')->get()
            ->map(fn (ProposalVersion $proposal): array => $this->proposal($proposal, $negotiation, $request->user()->id, $calculator));

        return Inertia::render('trade', [
            'section' => 'negotiation',
            'negotiation' => $this->negotiation($negotiation, $request->user()->id, $proposals->firstWhere('version', $negotiation->active_proposal_version)),
            'proposals' => $proposals,
            'entries' => DB::table('inventory_entries')->join('catalog_items', 'catalog_items.id', '=', 'inventory_entries.catalog_item_id')
                ->where('inventory_entries.user_id', $request->user()->id)->whereNull('inventory_entries.archived_at')->where('inventory_entries.quantity', '>', 0)
                ->orderBy('catalog_items.canonical_name')->get(['inventory_entries.public_id as id', 'catalog_items.canonical_name as name', 'inventory_entries.quantity']),
            'reportingEnabled' => false,
        ]);
    }

    private function listingSide(int $versionId, string $side): string
    {
        return DB::table('listing_version_items')->join('catalog_items', 'catalog_items.id', '=', 'listing_version_items.catalog_item_id')
            ->where('listing_version_id', $versionId)->where('side', $side)->orderBy('listing_version_items.id')
            ->get(['canonical_name', 'quantity'])->map(fn ($item): string => $item->canonical_name.($item->quantity > 1 ? ' ×'.$item->quantity : ''))->join(' + ') ?: '—';
    }

    private function negotiation(Negotiation $negotiation, int $viewerId, ?array $activeProposal = null): array
    {
        return [
            'id' => $negotiation->public_id,
            'alias' => $viewerId === $negotiation->initiator_id ? $negotiation->owner_alias : $negotiation->initiator_alias,
            'status' => $negotiation->status,
            'expiresAt' => $negotiation->expires_at->toIso8601String(),
            'updatedAt' => $negotiation->last_activity_at->toIso8601String(),
            'activeProposal' => $activeProposal,
        ];
    }

    private function proposal(ProposalVersion $proposal, Negotiation $negotiation, int $viewerId, WflCalculator $calculator): array
    {
        $snapshotVersion = $proposal->value_snapshot_id ? DB::table('value_snapshots')->where('id', $proposal->value_snapshot_id)->value('version') : null;
        $items = DB::table('proposal_items')->join('inventory_entries', 'inventory_entries.id', '=', 'proposal_items.inventory_entry_id')
            ->join('catalog_items', 'catalog_items.id', '=', 'proposal_items.catalog_item_id')->where('proposal_version_id', $proposal->id)
            ->orderBy('proposal_items.id')->get(['proposal_items.id', 'proposal_items.owner_user_id', 'inventory_entries.public_id as inventory_entry_id', 'catalog_items.canonical_name', 'proposal_items.quantity', 'proposal_items.unit_value'])
            ->map(fn ($item): array => [
                'id' => (string) $item->id,
                'inventoryEntryId' => $item->inventory_entry_id,
                'name' => $item->canonical_name,
                'quantity' => $item->quantity,
                'unitValue' => $item->unit_value === null ? null : (float) $item->unit_value,
                'side' => (int) $item->owner_user_id === $viewerId ? 'mine' : 'theirs',
            ]);
        $wfl = $calculator->calculate(
            $items->where('side', 'mine')->map(fn (array $item): array => ['id' => $item['id'], 'quantity' => $item['quantity'], 'unit_value' => $item['unitValue']])->values()->all(),
            $items->where('side', 'theirs')->map(fn (array $item): array => ['id' => $item['id'], 'quantity' => $item['quantity'], 'unit_value' => $item['unitValue']])->values()->all(),
            $this->threshold(),
            $snapshotVersion,
            'viewer',
        )->toArray();

        return [
            'id' => $proposal->public_id,
            'version' => $proposal->version,
            'status' => $proposal->status,
            'snapshotVersion' => $snapshotVersion ?? '—',
            'giveTotal' => $wfl['giveTotal'],
            'receiveTotal' => $wfl['receiveTotal'],
            'deltaPercent' => $wfl['deltaPercent'],
            'perspectiveStatus' => $wfl['status'],
            'expiresAt' => $proposal->expires_at->toIso8601String(),
            'items' => $items,
            'canAccept' => $proposal->status === 'active' && $proposal->expires_at->isFuture()
                && ! DB::table('proposal_acceptances')->where('proposal_version_id', $proposal->id)->where('user_id', $viewerId)->exists(),
        ];
    }

    private function threshold(): float
    {
        $stored = DB::table('system_settings')->where('key', 'fair_threshold_percent')->value('value');

        return $stored === null ? 10.0 : (float) (json_decode((string) $stored, true) ?? 10);
    }
}
