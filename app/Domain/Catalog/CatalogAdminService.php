<?php

namespace App\Domain\Catalog;

use App\Domain\Audit\AuditRecorder;
use App\Models\CatalogItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CatalogAdminService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param list<string> $aliases */
    public function update(CatalogItem $item, User $actor, bool $active, array $aliases, ?Request $request = null): CatalogItem
    {
        $aliases = array_values(array_unique(array_map(fn (string $alias): string => trim($alias), array_filter($aliases))));
        $this->assertNamesAreUnambiguous($item, $aliases);

        return DB::transaction(function () use ($item, $actor, $active, $aliases, $request): CatalogItem {
            $locked = CatalogItem::query()->lockForUpdate()->findOrFail($item->id);
            $before = ['is_active' => $locked->is_active, 'aliases' => $locked->aliases ?? []];
            $locked->forceFill(['is_active' => $active, 'aliases' => $aliases])->save();
            $this->audit->record($actor, 'catalog_item.updated', 'catalog_item', $locked->public_id, $before, [
                'is_active' => $locked->is_active,
                'aliases' => $locked->aliases,
            ], request: $request);

            return $locked;
        });
    }

    /** @param list<string> $aliases */
    private function assertNamesAreUnambiguous(CatalogItem $item, array $aliases): void
    {
        $candidateNames = collect([$item->canonical_name, ...$aliases])->map(fn (string $name) => mb_strtolower(trim($name)))->filter()->unique();
        $conflict = CatalogItem::query()->whereKeyNot($item->id)->get(['canonical_name', 'aliases'])->contains(function (CatalogItem $other) use ($candidateNames): bool {
            $otherNames = collect([$other->canonical_name, ...($other->aliases ?? [])])->map(fn (string $name) => mb_strtolower(trim($name)));

            return $candidateNames->intersect($otherNames)->isNotEmpty();
        });

        abort_if($conflict, 422, 'Canonical name or alias conflicts with another catalog item.');
    }
}
