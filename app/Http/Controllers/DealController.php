<?php

namespace App\Http\Controllers;

use App\Domain\Trade\DealService;
use App\Models\Deal;
use App\Models\Negotiation;
use App\Models\PreDealVersion;
use App\Models\ProposalVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class DealController extends Controller
{
    public function proposeServer(Request $request, string $negotiation, DealService $service): RedirectResponse
    {
        $data = $request->validate(['serverType' => ['required', Rule::in(['public_server', 'private_server'])]]);
        $room = Negotiation::query()->where('public_id', $negotiation)->firstOrFail();
        $service->proposeServer($request->user(), $room, $data['serverType']);

        return back()->with('success', 'Server choice proposed for both participants.');
    }

    public function acceptServer(Request $request, string $negotiation, DealService $service): RedirectResponse
    {
        $data = $request->validate(['preDealId' => ['required', 'uuid']]);
        $room = Negotiation::query()->where('public_id', $negotiation)->firstOrFail();
        $preDeal = PreDealVersion::query()->where('public_id', $data['preDealId'])->where('negotiation_id', $room->id)->firstOrFail();
        $deal = $service->acceptServer($request->user(), $preDeal);

        return $deal->exists
            ? to_route('deals.show', $deal->public_id)->with('success', 'Server choice accepted. Final acceptance is ready.')
            : back()->with('success', 'Server choice accepted. Waiting for the other participant.');
    }

    public function finalAccept(Request $request, string $negotiation, DealService $service): RedirectResponse
    {
        $data = $request->validate(['disclaimerAccepted' => ['accepted']]);
        $room = Negotiation::query()->where('public_id', $negotiation)->firstOrFail();
        $deal = Deal::query()->where('negotiation_id', $room->id)->firstOrFail();
        $deal = $service->finalAccept($request->user(), $deal, (bool) $data['disclaimerAccepted']);

        return to_route('deals.show', $deal->public_id)->with(
            'success',
            $deal->status === 'revealed' ? 'Deal finalized. Counterparty identity is now available.' : 'Final acceptance recorded. Waiting for the other participant.',
        );
    }

    public function show(Request $request, string $deal, DealService $service): Response
    {
        $record = Deal::query()->where('public_id', $deal)->firstOrFail();
        $view = $service->view($request->user(), $record);
        $snapshot = $view['snapshot'] ?? [];
        $identity = $view['counterpartyIdentity'];
        $preDeal = PreDealVersion::query()->findOrFail($record->pre_deal_version_id);
        $proposal = ProposalVersion::query()->findOrFail($record->proposal_version_id);

        return Inertia::render('trade', [
            'section' => 'deal',
            'deal' => [
                'id' => $view['id'],
                'negotiationId' => $view['negotiationId'],
                'status' => $view['status'],
                'serverType' => $snapshot['serverType'] ?? $preDeal->server_type,
                'snapshotVersion' => $snapshot['valueSnapshotVersion'] ?? DB::table('value_snapshots')->where('id', $proposal->value_snapshot_id)->value('version') ?? '—',
                'expiresAt' => $view['expiresAt']->toIso8601String(),
                'revealExpiresAt' => $identity['availableUntil'] ?? null,
                'feeQuote' => $view['feeQuote'],
                'identity' => $identity ? ['usn' => $identity['usn'], 'ign' => $identity['ign']] : null,
                'finalAcceptedByMe' => DB::table('deal_acceptances')->where('deal_id', $record->id)->where('user_id', $request->user()->id)->exists(),
            ],
        ]);
    }
}
