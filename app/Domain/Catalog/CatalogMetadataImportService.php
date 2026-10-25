<?php

namespace App\Domain\Catalog;

use App\Models\CatalogMetadataImport;
use Illuminate\Support\Carbon;

final class CatalogMetadataImportService
{
    public function __construct(private readonly CatalogMetadataSource $source) {}

    /** @return array{status:string, import:CatalogMetadataImport} */
    public function import(): array
    {
        try {
            $payload = $this->source->fetch();
            $rows = $this->validateRows($payload['rows'] ?? []);
            $checksum = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $import = CatalogMetadataImport::create([
                'source_key' => (string) ($payload['metadata']['source_key'] ?? 'catalog-metadata'),
                'status' => 'valid',
                'checksum' => $checksum,
                'source_timestamp' => isset($payload['source_timestamp']) ? Carbon::parse($payload['source_timestamp']) : null,
                'validation_errors' => [],
                'payload' => $rows,
                'imported_at' => now(),
            ]);

            return ['status' => 'valid', 'import' => $import];
        } catch (\Throwable $exception) {
            $import = CatalogMetadataImport::create([
                'source_key' => 'catalog-metadata',
                'status' => 'failed',
                'checksum' => null,
                'validation_errors' => [['code' => 'source_unavailable', 'message' => $exception->getMessage()]],
                'payload' => [],
                'imported_at' => now(),
            ]);

            return ['status' => 'failed', 'import' => $import];
        }
    }

    /** @param mixed $rows @return list<array{category:string,name:string,aliases:list<string>,image_url:?string,source_url:?string}> */
    private function validateRows(mixed $rows): array
    {
        if (! is_array($rows) || $rows === []) {
            throw new \InvalidArgumentException('Metadata response contains no rows.');
        }

        $seen = [];
        $validated = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new \InvalidArgumentException('Metadata row must be an object.');
            }
            $category = trim((string) ($row['category'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if (! in_array($category, ['skin', 'boat', 'fishing_rod'], true) || $name === '') {
                throw new \InvalidArgumentException('Metadata row has an invalid category or name.');
            }
            $key = $category.'|'.mb_strtolower($name);
            if (isset($seen[$key])) {
                throw new \InvalidArgumentException('Metadata response contains duplicate items.');
            }
            $seen[$key] = true;
            $aliases = array_values(array_unique(array_filter(array_map(
                fn (mixed $alias): string => trim((string) $alias),
                is_array($row['aliases'] ?? null) ? $row['aliases'] : []
            ))));
            $validated[] = [
                'category' => $category,
                'name' => $name,
                'aliases' => $aliases,
                'image_url' => isset($row['image_url']) ? (string) $row['image_url'] : null,
                'source_url' => isset($row['source_url']) ? (string) $row['source_url'] : null,
            ];
        }

        return $validated;
    }
}
