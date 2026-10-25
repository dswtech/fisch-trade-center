<?php

namespace App\Domain\Trade;

use Illuminate\Support\Facades\DB;

final class PhaseTwoExpiryService
{
    public function expire(): array
    {
        return DB::transaction(function (): array {
            $proposals = DB::table('proposal_versions')->where('status', 'active')->where('expires_at', '<=', now())->update(['status' => 'expired', 'updated_at' => now()]);
            $negotiations = DB::table('negotiations')->whereIn('status', ['open', 'proposed', 'countered'])->where('expires_at', '<=', now())->update(['status' => 'expired', 'updated_at' => now()]);
            $preDeals = DB::table('pre_deal_versions')->where('status', 'active')->where('expires_at', '<=', now())->update(['status' => 'expired', 'updated_at' => now()]);
            $deals = DB::table('deals')->whereIn('status', ['pre_deal', 'awaiting_final_acceptance'])->where('expires_at', '<=', now())->update(['status' => 'expired', 'updated_at' => now()]);
            return compact('proposals', 'negotiations', 'preDeals', 'deals');
        });
    }
}
