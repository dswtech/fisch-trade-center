<?php

namespace App\Domain\Value;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ValueImportService
{
    public function __construct(
        private readonly ValueSource $source,
        private readonly ValueImportValidator $validator,
    ) {}

    /** @return array{public_id: string, status: string, errors: list<array{row: int, code: string}>} */
    public function import(): array
    {
        try {
            $payload = $this->source->fetch();
            $rows = $payload['rows'];
            $sourceKey = $payload['source']['key'];
            $result = $this->validator->validate($rows);
            $errors = [...$result['errors'], ...$this->mappingErrors($rows), ...$this->anomalyErrors($rows, $sourceKey)];
            $checksum = $result['checksum'];
            $sourceTimestamp = $payload['source_timestamp'];
        } catch (\Throwable) {
            $rows = [];
            $errors = [['row' => -1, 'code' => 'source_fetch_failed']];
            $checksum = hash('sha256', 'source_fetch_failed:'.now()->toIso8601String());
            $sourceTimestamp = now();
            $sourceKey = 'google-sheets-skins';
        }

        $publicId = (string) Str::uuid();
        $status = $errors === [] ? 'valid' : 'invalid';
        DB::table('value_imports')->insert([
            'public_id' => $publicId,
            'source_key' => $sourceKey,
            'status' => $status,
            'checksum' => $checksum,
            'source_timestamp' => $sourceTimestamp,
            'validation_errors' => json_encode($errors, JSON_THROW_ON_ERROR),
            'payload' => json_encode($rows, JSON_THROW_ON_ERROR),
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['public_id' => $publicId, 'status' => $status, 'errors' => $errors];
    }

    /** @param list<array<string, mixed>> $rows
     *  @return list<array{row: int, code: string}>
     */
    private function mappingErrors(array &$rows): array
    {
        $items = DB::table('catalog_items')
            ->join('catalog_categories', 'catalog_categories.id', '=', 'catalog_items.category_id')
            ->where('catalog_items.is_active', true)
            ->where('catalog_categories.key', config('value_source.category', 'skin'))
            ->get(['catalog_items.public_id', 'catalog_items.canonical_name', 'catalog_items.aliases']);
        $index = [];
        foreach ($items as $item) {
            $names = [$item->canonical_name, ...((array) json_decode($item->aliases ?? '[]', true))];
            foreach ($names as $name) {
                $index[$this->normalize((string) $name)][] = $item->public_id;
            }
        }

        $errors = [];
        $mappedItems = [];
        foreach ($rows as $position => &$row) {
            $matches = array_values(array_unique($index[$this->normalize((string) ($row['name'] ?? ''))] ?? []));
            if (count($matches) !== 1) {
                $errors[] = ['row' => $position, 'code' => $matches === [] ? 'unmapped_catalog_item' : 'ambiguous_catalog_item'];
                continue;
            }
            $row['catalog_item_public_id'] = $matches[0];
            if (isset($mappedItems[$matches[0]])) {
                $errors[] = ['row' => $position, 'code' => 'duplicate_catalog_mapping'];
            }
            $mappedItems[$matches[0]] = true;
        }
        unset($row);

        return $errors;
    }

    /** @param list<array<string, mixed>> $rows
     *  @return list<array{row: int, code: string}>
     */
    private function anomalyErrors(array $rows, string $sourceKey): array
    {
        $snapshotId = DB::table('value_snapshots')
            ->join('value_imports', 'value_imports.id', '=', 'value_snapshots.value_import_id')
            ->where('value_imports.source_key', $sourceKey)
            ->whereNotNull('value_snapshots.published_at')
            ->orderByDesc('value_snapshots.published_at')
            ->orderByDesc('value_snapshots.id')
            ->value('value_snapshots.id');
        if (! $snapshotId) {
            return [];
        }

        $baseline = DB::table('catalog_item_values')
            ->join('catalog_items', 'catalog_items.id', '=', 'catalog_item_values.catalog_item_id')
            ->join('catalog_categories', 'catalog_categories.id', '=', 'catalog_items.category_id')
            ->where('catalog_item_values.snapshot_id', $snapshotId)
            ->where('catalog_categories.key', config('value_source.category', 'skin'))
            ->pluck('catalog_item_values.unit_value', 'catalog_items.public_id');
        if ($baseline->isEmpty()) {
            return [];
        }

        $threshold = (float) config('value_source.anomaly_threshold_percent', 25);
        $errors = [];
        $countDelta = abs(count($rows) - $baseline->count()) / $baseline->count() * 100;
        if ($countDelta > $threshold) {
            $errors[] = ['row' => -1, 'code' => 'anomalous_row_count'];
        }
        foreach ($rows as $position => $row) {
            $publicId = $row['catalog_item_public_id'] ?? null;
            if (! $publicId || ! $baseline->has($publicId) || ! is_numeric($row['value'] ?? null)) {
                continue;
            }
            $previous = (float) $baseline[$publicId];
            $current = (float) $row['value'];
            $delta = $previous === 0.0 ? ($current === 0.0 ? 0 : 100) : abs($current - $previous) / $previous * 100;
            if ($delta > $threshold) {
                $errors[] = ['row' => $position, 'code' => 'anomalous_value_change'];
            }
        }

        return $errors;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->squish()->ascii()->toString();
    }
}
