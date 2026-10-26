<?php

namespace App\Http\Controllers;

use App\Domain\Trade\PhaseFourExpiryService;
use App\Models\Deal;
use App\Models\PreDealVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class AdminTimeControlController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin-time-controls', [
            'preDeals' => DB::table('pre_deal_versions as pd')->join('negotiations as n', 'n.id', '=', 'pd.negotiation_id')
                ->leftJoin('deals as d', 'd.pre_deal_version_id', '=', 'pd.id')
                ->whereIn('pd.status', ['active', 'accepted'])->where(fn ($query) => $query->whereNull('d.id')->orWhere('d.status', '!=', 'revealed'))
                ->orderBy('pd.expires_at')->get(['pd.public_id as id', 'n.public_id as negotiationId', 'pd.server_type as serverType', 'pd.status', 'pd.expires_at as expiresAt']),
            'reveals' => DB::table('deals as d')->join('negotiations as n', 'n.id', '=', 'd.negotiation_id')
                ->join('reveal_entitlements as re', 're.deal_id', '=', 'd.id')->where('d.status', 'revealed')->where('re.expires_at', '>', now())
                ->groupBy('d.id', 'd.public_id', 'n.public_id')->orderByRaw('MAX(re.expires_at)')
                ->get(['d.public_id as id', 'n.public_id as negotiationId', DB::raw('MAX(re.expires_at) as expiresAt')]),
        ]);
    }

    public function expirePreDeal(Request $request, string $preDeal, PhaseFourExpiryService $service): RedirectResponse
    {
        $record = PreDealVersion::query()->where('public_id', $preDeal)->firstOrFail();
        $service->expirePreDeal($record, $request->user(), $request);

        return back()->with('success', 'Pre-deal expired for non-production testing.');
    }

    public function expireReveal(Request $request, string $deal, PhaseFourExpiryService $service): RedirectResponse
    {
        $record = Deal::query()->where('public_id', $deal)->firstOrFail();
        $service->expireReveal($record, $request->user(), $request);

        return back()->with('success', 'Reveal access expired for non-production testing.');
    }
}
