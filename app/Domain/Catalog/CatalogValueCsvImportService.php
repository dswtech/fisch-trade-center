<?php

namespace App\Domain\Catalog;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CatalogValueCsvImportService
{
    private const HEADERS = ['item_key', 'category', 'name', 'aliases', 'value', 'offer_range', 'status'];

    /** @return array{public_id:string,status:string,errors:array,warnings:array,preview:array} */
    public function stage(UploadedFile $file, User $actor): array
    {
        [$rows, $errors] = $this->parse($file->getRealPath());
        [$rows, $mappingErrors, $warnings, $preview] = $this->prepare($rows);
        $errors = [...$errors, ...$mappingErrors];
        $checksum = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $publicId = (string) Str::uuid();
        $status = $errors === [] ? 'valid' : 'invalid';

        DB::table('value_imports')->insert([
            'public_id' => $publicId,
            'source_key' => 'admin-csv',
            'status' => $status,
            'checksum' => $checksum,
            'source_timestamp' => now(),
            'validation_errors' => json_encode($errors, JSON_THROW_ON_ERROR),
            'payload' => json_encode(['rows' => $rows, 'warnings' => $warnings, 'preview' => $preview], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('audit_events')->insert([
            'public_id' => (string) Str::uuid(), 'actor_id' => $actor->id,
            'event_type' => 'catalog_value_import.staged', 'subject_type' => 'value_import',
            'subject_public_id' => $publicId,
            'metadata' => json_encode(['status' => $status, 'rows' => count($rows), 'warnings' => count($warnings)]),
            'created_at' => now(),
        ]);

        return ['public_id' => $publicId, 'status' => $status, 'errors' => $errors, 'warnings' => $warnings, 'preview' => $preview];
    }

    /** @return array{snapshot_id:int,already_published:bool} */
    public function publish(string $publicId, User $actor, bool $confirmWarnings): array
    {
        $import = DB::table('value_imports')->where('public_id', $publicId)->where('status', 'valid')->first();
        abort_unless($import, 422, 'Import CSV valid tidak ditemukan.');
        $payload = json_decode($import->payload ?? '{}', true, flags: JSON_THROW_ON_ERROR);
        abort_if(($payload['warnings'] ?? []) !== [] && ! $confirmWarnings, 422, 'Konfirmasi warning sebelum publish.');

        return DB::transaction(function () use ($import, $payload, $actor): array {
            $locked = DB::table('value_imports')->where('id', $import->id)->lockForUpdate()->first();
            $existing = DB::table('value_snapshots')->where('value_import_id', $locked->id)->value('id');
            if ($existing) {
                return ['snapshot_id' => (int) $existing, 'already_published' => true];
            }

            $categoryIds = $this->categoryIds();
            $itemIds = [];
            foreach ($payload['rows'] as $row) {
                $item = DB::table('catalog_items')->where('public_id', $row['item_key'])->lockForUpdate()->first();
                $attributes = [
                    'category_id' => $categoryIds[$row['category']],
                    'canonical_name' => $row['name'],
                    'aliases' => json_encode($row['aliases'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'is_active' => $row['status'] === 'active',
                    'provenance' => json_encode(['source' => 'admin-csv'], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ];
                if ($item) {
                    DB::table('catalog_items')->where('id', $item->id)->update($attributes);
                    $itemIds[$row['item_key']] = $item->id;
                } else {
                    $itemIds[$row['item_key']] = DB::table('catalog_items')->insertGetId([
                        'public_id' => $row['item_key'], ...$attributes, 'created_at' => now(),
                    ]);
                }
            }

            $activeSnapshotId = DB::table('value_snapshots')->whereNotNull('published_at')->orderByDesc('published_at')->orderByDesc('id')->value('id');
            $values = $activeSnapshotId
                ? DB::table('catalog_item_values')->where('snapshot_id', $activeSnapshotId)->get()->keyBy('catalog_item_id')->map(fn ($value): array => ['unit_value' => $value->unit_value, 'offer_range' => $value->offer_range])->all()
                : [];
            foreach ($payload['rows'] as $row) {
                $itemId = $itemIds[$row['item_key']];
                if ($row['value'] === null) {
                    unset($values[$itemId]);
                } else {
                    $values[$itemId] = ['unit_value' => $row['value'], 'offer_range' => $row['offer_range']];
                }
            }

            $snapshotId = DB::table('value_snapshots')->insertGetId([
                'public_id' => (string) Str::uuid(), 'value_import_id' => $locked->id,
                'version' => 'csv-'.now()->format('YmdHis').'-'.substr($locked->public_id, 0, 8),
                'checksum' => hash('sha256', $locked->checksum.'|'.$locked->public_id), 'source_timestamp' => $locked->source_timestamp,
                'published_at' => now(), 'published_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($values !== []) {
                DB::table('catalog_item_values')->insert(collect($values)->map(fn (array $value, int|string $itemId): array => [
                    'snapshot_id' => $snapshotId, 'catalog_item_id' => (int) $itemId,
                    'unit_value' => $value['unit_value'], 'offer_range' => $value['offer_range'],
                ])->values()->all());
            }
            DB::table('audit_events')->insert([
                'public_id' => (string) Str::uuid(), 'actor_id' => $actor->id,
                'event_type' => 'catalog_value_import.published', 'subject_type' => 'value_import',
                'subject_public_id' => $locked->public_id,
                'metadata' => json_encode(['snapshot_id' => $snapshotId, 'preview' => $payload['preview'] ?? []]),
                'created_at' => now(),
            ]);

            return ['snapshot_id' => $snapshotId, 'already_published' => false];
        });
    }

    /** @return list<list<string>> */
    public function templateRows(): array
    {
        $snapshotId = DB::table('value_snapshots')->whereNotNull('published_at')->orderByDesc('published_at')->orderByDesc('id')->value('id');

        return DB::table('catalog_items as ci')->join('catalog_categories as cc', 'cc.id', '=', 'ci.category_id')
            ->leftJoin('catalog_item_values as civ', fn ($join) => $join->on('civ.catalog_item_id', '=', 'ci.id')->where('civ.snapshot_id', $snapshotId ?? 0))
            ->whereIn('cc.key', array_keys((array) config('catalog.categories')))
            ->orderBy('cc.key')->orderBy('ci.canonical_name')
            ->get(['ci.public_id', 'cc.key as category', 'ci.canonical_name', 'ci.aliases', 'civ.unit_value', 'civ.offer_range', 'ci.is_active'])
            ->map(fn ($row): array => [
                $row->public_id, $row->category, $this->safeCell($row->canonical_name),
                $this->safeCell(implode('|', json_decode($row->aliases ?? '[]', true) ?: [])),
                $row->unit_value === null ? '' : (string) $row->unit_value,
                $this->safeCell((string) ($row->offer_range ?? '')), $row->is_active ? 'active' : 'inactive',
            ])->all();
    }

    /** @return array{0:list<array<string,string>>,1:list<array{row:int,code:string}>} */
    private function parse(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return [[], [['row' => -1, 'code' => 'file_unreadable']]];
        }
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return [[], [['row' => -1, 'code' => 'missing_header']]];
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        if ($header !== self::HEADERS) {
            fclose($handle);
            return [[], [['row' => 1, 'code' => 'invalid_header']]];
        }
        $rows = [];
        $errors = [];
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if (count($values) === 1 && trim((string) $values[0]) === '') {
                continue;
            }
            if (count($values) !== count(self::HEADERS)) {
                $errors[] = ['row' => $line, 'code' => 'invalid_column_count'];
                continue;
            }
            if (! mb_check_encoding(implode('', $values), 'UTF-8')) {
                $errors[] = ['row' => $line, 'code' => 'invalid_utf8'];
                continue;
            }
            $rows[] = array_combine(self::HEADERS, array_map(fn ($value): string => trim((string) $value), $values));
            if (count($rows) > 10000) {
                $errors[] = ['row' => $line, 'code' => 'row_limit_exceeded'];
                break;
            }
        }
        fclose($handle);

        return [$rows, $errors];
    }

    /** @param list<array<string,string>> $rows
     * @return array{0:list<array<string,mixed>>,1:array,2:array,3:array}
     */
    private function prepare(array $rows): array
    {
        $categories = array_keys((array) config('catalog.categories'));
        $existing = DB::table('catalog_items as ci')->join('catalog_categories as cc', 'cc.id', '=', 'ci.category_id')
            ->whereIn('cc.key', $categories)->get(['ci.public_id', 'ci.canonical_name', 'ci.aliases', 'ci.is_active', 'cc.key as category']);
        $byKey = $existing->keyBy('public_id');
        $nameIndex = [];
        $canonicalIndex = [];
        foreach ($existing as $item) {
            $canonicalIndex[$item->category][$this->normalize($item->canonical_name)][] = $item->public_id;
            foreach ([$item->canonical_name, ...(json_decode($item->aliases ?? '[]', true) ?: [])] as $name) {
                $nameIndex[$item->category][$this->normalize((string) $name)][] = $item->public_id;
            }
        }
        $snapshotId = DB::table('value_snapshots')->whereNotNull('published_at')->orderByDesc('published_at')->orderByDesc('id')->value('id');
        $currentValues = $snapshotId ? DB::table('catalog_item_values as civ')->join('catalog_items as ci', 'ci.id', '=', 'civ.catalog_item_id')->where('civ.snapshot_id', $snapshotId)->pluck('civ.unit_value', 'ci.public_id') : collect();
        $errors = [];
        $warnings = [];
        $prepared = [];
        $seenKeys = [];
        $seenNames = [];
        $preview = ['created' => 0, 'updated' => 0, 'deactivated' => 0, 'value_changed' => 0, 'value_cleared' => 0];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $category = $row['category'];
            $name = $row['name'];
            if (! in_array($category, $categories, true)) {
                $errors[] = ['row' => $line, 'code' => 'invalid_category'];
            }
            if ($name === '' || preg_match('/^[=+\-@]/', $name)) {
                $errors[] = ['row' => $line, 'code' => 'invalid_name'];
            }
            if (! in_array($row['status'], ['active', 'inactive'], true)) {
                $errors[] = ['row' => $line, 'code' => 'invalid_status'];
            }
            $aliases = array_values(array_unique(array_filter(array_map('trim', explode('|', $row['aliases'])))));
            if (collect($aliases)->contains(fn (string $alias): bool => (bool) preg_match('/^[=+\-@]/', $alias))) {
                $errors[] = ['row' => $line, 'code' => 'invalid_alias'];
            }
            $value = null;
            if ($row['value'] !== '') {
                if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $row['value'])) {
                    $errors[] = ['row' => $line, 'code' => 'invalid_value'];
                } else {
                    $value = $row['value'];
                }
            }
            $itemKey = $row['item_key'];
            $matched = null;
            if ($itemKey !== '') {
                $matched = $byKey->get($itemKey);
                if (! Str::isUuid($itemKey) || ! $matched) {
                    $errors[] = ['row' => $line, 'code' => 'unknown_item_key'];
                }
            } elseif (in_array($category, $categories, true) && $name !== '') {
                $matches = array_values(array_unique($nameIndex[$category][$this->normalize($name)] ?? []));
                if (count($matches) > 1) {
                    $errors[] = ['row' => $line, 'code' => 'ambiguous_catalog_item'];
                } elseif (count($matches) === 1) {
                    $itemKey = $matches[0];
                    $matched = $byKey->get($itemKey);
                } else {
                    $itemKey = (string) Str::uuid();
                }
            }
            if ($itemKey !== '' && isset($seenKeys[$itemKey])) {
                $errors[] = ['row' => $line, 'code' => 'duplicate_item_key'];
            }
            $seenKeys[$itemKey] = true;
            $nameKey = $category.'|'.$this->normalize($name);
            $canonicalConflicts = array_values(array_filter(
                $canonicalIndex[$category][$this->normalize($name)] ?? [],
                fn (string $existingKey): bool => $existingKey !== $itemKey,
            ));
            if ($canonicalConflicts !== []) {
                $errors[] = ['row' => $line, 'code' => 'duplicate_name'];
            }
            if (isset($seenNames[$nameKey])) {
                $errors[] = ['row' => $line, 'code' => 'duplicate_name'];
            }
            $seenNames[$nameKey] = true;

            $matched ? $preview['updated']++ : $preview['created']++;
            if ($row['status'] === 'inactive' && (! $matched || $matched->is_active)) {
                $preview['deactivated']++;
            }
            $oldValue = $currentValues->get($itemKey);
            if ($value === null && $oldValue !== null) {
                $preview['value_cleared']++;
            } elseif ($value !== null && ($oldValue === null || (float) $oldValue !== (float) $value)) {
                $preview['value_changed']++;
                if ($oldValue !== null) {
                    $delta = (float) $oldValue === 0.0 ? 100.0 : abs((float) $value - (float) $oldValue) / (float) $oldValue * 100;
                    if ($delta > 25) {
                        $warnings[] = ['row' => $line, 'code' => 'value_change_over_25_percent'];
                    }
                }
            }
            $prepared[] = [
                'item_key' => $itemKey, 'category' => $category, 'name' => $name,
                'aliases' => $aliases, 'value' => $value, 'offer_range' => $row['offer_range'] === '' ? null : $row['offer_range'],
                'status' => $row['status'],
            ];
        }

        return [$prepared, $errors, $warnings, $preview];
    }

    /** @return array<string,int> */
    private function categoryIds(): array
    {
        $ids = [];
        foreach ((array) config('catalog.categories') as $key => $name) {
            $id = DB::table('catalog_categories')->where('key', $key)->value('id');
            $ids[$key] = $id ?: DB::table('catalog_categories')->insertGetId([
                'public_id' => (string) Str::uuid(), 'key' => $key, 'name' => $name,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->squish()->ascii()->toString();
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
