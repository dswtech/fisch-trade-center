<?php
namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();
        return [...parent::share($request),
            'auth' => ['user' => $user ? ['id' => $user->public_id, 'name' => $user->name, 'emailVerified' => $user->hasVerifiedEmail(), 'role' => $user->role] : null],
            'tradeActions' => fn (): array => $user ? $this->pendingTradeActions($user->id) : ['pendingCount' => 0, 'items' => []],
            'preferences' => ['locale' => $user?->locale, 'theme' => $user?->theme],
            'flash' => ['success' => fn () => $request->session()->get('success'), 'error' => fn () => $request->session()->get('error')],
        ];
    }

    private function pendingTradeActions(int $userId): array
    {
        $participant = fn ($query) => $query->where('n.initiator_id', $userId)->orWhere('n.owner_id', $userId);
        $proposals = DB::table('negotiations as n')->join('proposal_versions as p', function ($join): void {
            $join->on('p.negotiation_id', '=', 'n.id')->on('p.version', '=', 'n.active_proposal_version');
        })->where($participant)->whereIn('n.status', ['proposed', 'countered'])->where('p.status', 'active')->where('p.expires_at', '>', now())
            ->where('p.proposer_id', '<>', $userId)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('proposal_acceptances as pa')->whereColumn('pa.proposal_version_id', 'p.id')->where('pa.user_id', $userId))
            ->get(['n.id', 'n.public_id', 'n.initiator_id', 'n.initiator_alias', 'n.owner_alias', 'n.last_activity_at', 'p.intent'])
            ->map(fn ($row): array => $this->tradeAction($row, $userId, $row->intent === 'request_more_items' ? 'request_more_items' : 'proposal', '/negotiations/'.$row->public_id));
        $serverChoices = DB::table('negotiations as n')->where($participant)->where('n.status', 'accepted')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('pre_deal_versions as pd')->whereColumn('pd.negotiation_id', 'n.id')->whereIn('pd.status', ['active', 'accepted'])->where('pd.expires_at', '>', now()))
            ->get(['n.id', 'n.public_id', 'n.initiator_id', 'n.initiator_alias', 'n.owner_alias', 'n.last_activity_at'])
            ->map(fn ($row): array => $this->tradeAction($row, $userId, 'server_choice', '/negotiations/'.$row->public_id));
        $preDeals = DB::table('negotiations as n')->join('pre_deal_versions as pd', 'pd.negotiation_id', '=', 'n.id')
            ->where($participant)->where('n.status', 'accepted')->where('pd.status', 'active')->where('pd.expires_at', '>', now())
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('pre_deal_acceptances as pda')->whereColumn('pda.pre_deal_version_id', 'pd.id')->where('pda.user_id', $userId))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('deals as d')->whereColumn('d.negotiation_id', 'n.id')->where('d.status', 'awaiting_final_acceptance')->where('d.expires_at', '>', now()))
            ->get(['n.id', 'n.public_id', 'n.initiator_id', 'n.initiator_alias', 'n.owner_alias', 'n.last_activity_at'])
            ->map(fn ($row): array => $this->tradeAction($row, $userId, 'pre_deal_acceptance', '/negotiations/'.$row->public_id));
        $deals = DB::table('negotiations as n')->join('deals as d', 'd.negotiation_id', '=', 'n.id')
            ->where($participant)->where('d.status', 'awaiting_final_acceptance')->where('d.expires_at', '>', now())
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('deal_acceptances as da')->whereColumn('da.deal_id', 'd.id')->where('da.user_id', $userId))
            ->get(['n.id', 'n.public_id', 'n.initiator_id', 'n.initiator_alias', 'n.owner_alias', 'n.last_activity_at', 'd.public_id as deal_public_id'])
            ->map(fn ($row): array => $this->tradeAction($row, $userId, 'final_acceptance', '/deals/'.$row->deal_public_id));

        $actions = $proposals->concat($serverChoices)->concat($preDeals)->concat($deals)
            ->sortByDesc('actionAt')->unique('negotiationId')->values();

        return ['pendingCount' => $actions->count(), 'items' => $actions->take(5)->all()];
    }

    private function tradeAction(object $row, int $userId, string $stage, string $href): array
    {
        return [
            'negotiationId' => $row->public_id,
            'alias' => (int) $row->initiator_id === $userId ? $row->owner_alias : $row->initiator_alias,
            'stage' => $stage,
            'actionAt' => Carbon::parse($row->last_activity_at)->toIso8601String(),
            'href' => $href,
        ];
    }
}
