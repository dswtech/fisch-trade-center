<?php

namespace App\Http\Controllers;

use App\Domain\Trade\NegotiationService;
use App\Models\Listing;
use App\Models\Negotiation;
use App\Models\ProposalVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class NegotiationController extends Controller
{
    public function store(Request $request, NegotiationService $service): RedirectResponse
    {
        $data = $this->items($request, ['listingId' => ['required', 'uuid']]);
        $listing = Listing::where('public_id', $data['listingId'])->firstOrFail();
        $negotiation = $service->start($request->user(), $listing, $data['items']);

        return to_route('negotiations.show', $negotiation->public_id)->with('success', 'Negotiation started.');
    }

    public function counter(Request $request, string $negotiation, NegotiationService $service): RedirectResponse
    {
        $data = $this->items($request);
        $negotiation = $this->participant($request, $negotiation);
        $active = ProposalVersion::where('negotiation_id', $negotiation->id)->where('version', $negotiation->active_proposal_version)->firstOrFail();
        $current = DB::table('proposal_items')->join('inventory_entries', 'inventory_entries.id', '=', 'proposal_items.inventory_entry_id')
            ->where('proposal_version_id', $active->id)->get(['proposal_items.side', 'inventory_entries.public_id as inventoryEntryId', 'proposal_items.quantity'])
            ->groupBy('side')->map(fn ($items): array => $items->map(fn ($item): array => ['inventoryEntryId' => $item->inventoryEntryId, 'quantity' => $item->quantity])->all());
        $initiator = $request->user()->id === $negotiation->initiator_id ? $data['items'] : $current->get('initiator', []);
        $owner = $request->user()->id === $negotiation->owner_id ? $data['items'] : $current->get('owner', []);
        $service->counter($request->user(), $negotiation, $initiator, $owner);

        return back()->with('success', 'Counteroffer sent.');
    }

    public function accept(Request $request, string $negotiation, NegotiationService $service): RedirectResponse
    {
        $data = $request->validate(['proposalId' => ['required', 'uuid']]);
        $negotiation = $this->participant($request, $negotiation);
        $proposal = ProposalVersion::where('public_id', $data['proposalId'])->where('negotiation_id', $negotiation->id)->firstOrFail();
        $service->accept($request->user(), $negotiation, $proposal->version);

        return back()->with('success', 'Proposal accepted.');
    }

    public function reject(Request $request, string $negotiation, NegotiationService $service): RedirectResponse
    {
        $negotiation = $this->participant($request, $negotiation);
        $service->reject($request->user(), $negotiation, $this->reason($request, 'declined'));

        return to_route('negotiations.index')->with('success', 'Negotiation rejected.');
    }

    public function cancel(Request $request, string $negotiation, NegotiationService $service): RedirectResponse
    {
        $negotiation = $this->participant($request, $negotiation);
        $service->cancel($request->user(), $negotiation, $this->reason($request, 'cancelled_by_user'));

        return to_route('negotiations.index')->with('success', 'Negotiation cancelled.');
    }

    private function participant(Request $request, string $publicId): Negotiation
    {
        $negotiation = Negotiation::where('public_id', $publicId)->firstOrFail();
        abort_unless($negotiation->isParticipant($request->user()->id), 404);

        return $negotiation;
    }

    private function items(Request $request, array $extra = []): array
    {
        return $request->validate($extra + [
            'items' => ['required', 'array', 'min:1', 'max:8'],
            'items.*.inventoryEntryId' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);
    }

    private function reason(Request $request, string $default): string
    {
        return $request->validate(['reasonCode' => ['nullable', Rule::in(['declined', 'not_interested', 'inventory_changed', 'cancelled_by_user'])]])['reasonCode'] ?? $default;
    }
}
