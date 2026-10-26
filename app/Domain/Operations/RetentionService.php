<?php

namespace App\Domain\Operations;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RetentionService
{
    public function plan(): array
    {
        $identityCutoff = now()->subDays((int) config('operations.identity_days'));
        $reportCutoff = now()->subDays((int) config('operations.report_days'));
        $negotiationCutoff = now()->subDays((int) config('operations.negotiation_days'));
        $historyCutoff = now()->subDays((int) config('operations.history_days'));

        return [
            'identity_snapshots' => DB::table('deal_identity_snapshots as ids')->join('deals as d', 'd.id', '=', 'ids.deal_id')->where('d.revealed_at', '<=', $identityCutoff)->count(),
            'reveal_entitlements' => DB::table('reveal_entitlements as re')->join('deals as d', 'd.id', '=', 're.deal_id')->where('d.revealed_at', '<=', $identityCutoff)->count(),
            'resolved_reports' => DB::table('reports')->whereIn('status', ['resolved', 'dismissed'])->where('resolved_at', '<=', $reportCutoff)->count(),
            'negotiation_graphs' => $this->staleNegotiations($negotiationCutoff)->count(),
            'deal_graphs' => $this->staleDeals($historyCutoff)->count(),
            'completed_suspensions' => $this->completedSuspensions($historyCutoff)->count(),
            'audit_events' => DB::table('audit_events')->where('created_at', '<=', $historyCutoff)->count(),
        ];
    }

    public function execute(): array
    {
        $deleted = collect(array_keys($this->plan()))->mapWithKeys(fn (string $key): array => [$key => 0])->all();
        $identityCutoff = now()->subDays((int) config('operations.identity_days'));
        $reportCutoff = now()->subDays((int) config('operations.report_days'));
        $negotiationCutoff = now()->subDays((int) config('operations.negotiation_days'));
        $historyCutoff = now()->subDays((int) config('operations.history_days'));

        DB::transaction(function () use (&$deleted, $identityCutoff, $reportCutoff, $historyCutoff): void {
            $oldDealIds = DB::table('deals')->where('revealed_at', '<=', $identityCutoff)->pluck('id');
            $deleted['reveal_entitlements'] += DB::table('reveal_entitlements')->whereIn('deal_id', $oldDealIds)->delete();
            $deleted['identity_snapshots'] += DB::table('deal_identity_snapshots')->whereIn('deal_id', $oldDealIds)->delete();
            $deleted['resolved_reports'] += DB::table('reports')->whereIn('status', ['resolved', 'dismissed'])->where('resolved_at', '<=', $reportCutoff)->delete();
            $deleted['completed_suspensions'] += $this->completedSuspensions($historyCutoff)->delete();
            $deleted['audit_events'] += DB::table('audit_events')->where('created_at', '<=', $historyCutoff)->delete();
        });

        $chunk = (int) config('operations.retention_chunk', 100);
        while (($ids = $this->staleDeals($historyCutoff)->limit($chunk)->pluck('n.id'))->isNotEmpty()) {
            $deleted['deal_graphs'] += DB::transaction(fn (): int => $this->deleteNegotiationGraphs($ids, true));
        }
        while (($ids = $this->staleNegotiations($negotiationCutoff)->limit($chunk)->pluck('n.id'))->isNotEmpty()) {
            $deleted['negotiation_graphs'] += DB::transaction(fn (): int => $this->deleteNegotiationGraphs($ids, false));
        }

        return $deleted;
    }

    private function staleNegotiations($cutoff)
    {
        return DB::table('negotiations as n')
            ->whereIn('n.status', ['accepted', 'rejected', 'cancelled', 'expired', 'invalidated'])
            ->where('n.last_activity_at', '<=', $cutoff)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('deals as d')->whereColumn('d.negotiation_id', 'n.id'))
            ->whereNotExists(fn ($query) => $this->retainedReport($query));
    }

    private function staleDeals($cutoff)
    {
        return DB::table('negotiations as n')->join('deals as d', 'd.negotiation_id', '=', 'n.id')
            ->whereIn('d.status', ['revealed', 'failed', 'cancelled', 'expired'])
            ->where('d.updated_at', '<=', $cutoff)
            ->whereNotExists(fn ($query) => $this->retainedReport($query));
    }

    private function retainedReport($query): void
    {
        $query->selectRaw('1')->from('reports as r')->where('r.target_type', 'negotiation')
            ->whereColumn('r.target_public_id', 'n.public_id');
    }

    private function completedSuspensions($cutoff)
    {
        return DB::table('suspensions')->where(function ($query) use ($cutoff): void {
            $query->where('lifted_at', '<=', $cutoff)
                ->orWhere(fn ($inner) => $inner->whereNull('lifted_at')->whereNotNull('ends_at')->where('ends_at', '<=', $cutoff));
        });
    }

    private function deleteNegotiationGraphs(Collection $negotiationIds, bool $withDeals): int
    {
        $proposalIds = DB::table('proposal_versions')->whereIn('negotiation_id', $negotiationIds)->pluck('id');
        $preDealIds = DB::table('pre_deal_versions')->whereIn('negotiation_id', $negotiationIds)->pluck('id');
        $dealIds = DB::table('deals')->whereIn('negotiation_id', $negotiationIds)->pluck('id');

        if ($withDeals) {
            DB::table('reveal_entitlements')->whereIn('deal_id', $dealIds)->delete();
            DB::table('deal_identity_snapshots')->whereIn('deal_id', $dealIds)->delete();
            DB::table('deal_acceptances')->whereIn('deal_id', $dealIds)->delete();
            DB::table('deals')->whereIn('id', $dealIds)->delete();
        }
        DB::table('pre_deal_acceptances')->whereIn('pre_deal_version_id', $preDealIds)->delete();
        DB::table('pre_deal_versions')->whereIn('id', $preDealIds)->delete();
        DB::table('proposal_acceptances')->whereIn('proposal_version_id', $proposalIds)->delete();
        DB::table('proposal_items')->whereIn('proposal_version_id', $proposalIds)->delete();
        DB::table('proposal_versions')->whereIn('id', $proposalIds)->delete();

        return DB::table('negotiations')->whereIn('id', $negotiationIds)->delete();
    }
}
