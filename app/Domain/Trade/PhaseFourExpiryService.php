<?php

namespace App\Domain\Trade;

use App\Domain\Audit\AuditRecorder;
use App\Models\Deal;
use App\Models\PreDealVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PhaseFourExpiryService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function expire(): array
    {
        return DB::transaction(function (): array {
            $deals = DB::table('deals')->where('status', 'awaiting_final_acceptance')->where('expires_at', '<=', now())
                ->update(['status' => 'expired', 'updated_at' => now()]);
            $preDeals = DB::table('pre_deal_versions')->whereIn('status', ['active', 'accepted'])->where('expires_at', '<=', now())
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('deals')
                    ->whereColumn('deals.pre_deal_version_id', 'pre_deal_versions.id')->where('deals.status', 'revealed'))
                ->update(['status' => 'expired', 'updated_at' => now()]);

            return ['pre_deals' => $preDeals, 'deals' => $deals];
        });
    }

    public function expirePreDeal(PreDealVersion $preDeal, User $actor, Request $request): void
    {
        DB::transaction(function () use ($preDeal, $actor, $request): void {
            $preDeal = PreDealVersion::query()->whereKey($preDeal->id)->lockForUpdate()->firstOrFail();
            $deal = Deal::query()->where('pre_deal_version_id', $preDeal->id)->lockForUpdate()->first();
            abort_if($deal?->status === 'revealed', 409, 'A revealed deal cannot be expired as a pre-deal.');
            abort_unless(in_array($preDeal->status, ['active', 'accepted'], true), 409, 'Pre-deal is already closed.');
            $preDeal->update(['status' => 'expired', 'expires_at' => now()->subSecond()]);
            if ($deal && $deal->status === 'awaiting_final_acceptance') {
                $deal->update(['status' => 'expired', 'expires_at' => now()->subSecond()]);
            }
            $this->audit->record($actor, 'pre_deal.manually_expired', 'pre_deal', $preDeal->public_id, request: $request);
        });
    }

    public function expireReveal(Deal $deal, User $actor, Request $request): void
    {
        DB::transaction(function () use ($deal, $actor, $request): void {
            $deal = Deal::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
            abort_unless($deal->status === 'revealed', 409, 'Only a revealed deal has reveal access.');
            DB::table('reveal_entitlements')->where('deal_id', $deal->id)->where('expires_at', '>', now())
                ->update(['expires_at' => now()->subSecond()]);
            $this->audit->record($actor, 'deal.reveal_manually_expired', 'deal', $deal->public_id, request: $request);
        });
    }
}
