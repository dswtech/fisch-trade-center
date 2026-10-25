<?php

namespace App\Domain\Trade;

use Illuminate\Support\Facades\DB;

final class NegotiationInvalidator
{
    public function forInventoryEntry(int $inventoryEntryId): int
    {
        $ids = DB::table('proposal_items')->join('proposal_versions', 'proposal_versions.id', '=', 'proposal_items.proposal_version_id')
            ->where('proposal_items.inventory_entry_id', $inventoryEntryId)->where('proposal_versions.status', 'active')
            ->pluck('proposal_versions.negotiation_id');
        return $this->invalidate($ids->all());
    }

    public function forListing(int $listingId): int
    {
        return $this->invalidate(DB::table('negotiations')->where('listing_id', $listingId)->pluck('id')->all());
    }

    private function invalidate(array $negotiationIds): int
    {
        if ($negotiationIds === []) {
            return 0;
        }
        DB::table('proposal_versions')->whereIn('negotiation_id', $negotiationIds)->where('status', 'active')->update(['status' => 'invalidated', 'updated_at' => now()]);
        return DB::table('negotiations')->whereIn('id', $negotiationIds)->whereIn('status', ['open', 'proposed', 'countered', 'accepted'])->update(['status' => 'invalidated', 'updated_at' => now()]);
    }
}
