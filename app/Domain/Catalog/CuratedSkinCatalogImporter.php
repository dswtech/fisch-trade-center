<?php

namespace App\Domain\Catalog;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CuratedSkinCatalogImporter
{
    /** @return array{created: int, matched: int, total: int} */
    public function import(): array
    {
        $manifest = database_path('data/skin-catalog-v1.php');
        $names = require $manifest;
        $normalized = array_map($this->normalize(...), $names);

        if (count($names) !== 231 || count(array_unique($normalized)) !== count($names) || in_array('', $normalized, true)) {
            throw new DomainException('Curated skin catalog manifest is invalid.');
        }

        return DB::transaction(function () use ($manifest, $names): array {
            $category = DB::table('catalog_categories')->where('key', 'skin')->first();
            if ($category && ! $category->is_active) {
                throw new DomainException('Skin catalog category is inactive.');
            }
            $categoryId = $category?->id ?? DB::table('catalog_categories')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'key' => 'skin',
                'name' => 'Skins',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $items = DB::table('catalog_items')->where('category_id', $categoryId)->get([
                'id', 'canonical_name', 'aliases', 'is_active',
            ]);
            $index = [];
            foreach ($items as $item) {
                foreach ([$item->canonical_name, ...((array) json_decode($item->aliases ?? '[]', true))] as $name) {
                    $index[$this->normalize((string) $name)][] = $item;
                }
            }

            $created = 0;
            $matched = 0;
            $checksum = hash_file('sha256', $manifest);
            foreach ($names as $name) {
                $matches = collect($index[$this->normalize($name)] ?? [])->unique('id')->values();
                if ($matches->count() > 1) {
                    throw new DomainException("Curated skin catalog mapping is ambiguous for {$name}.");
                }
                if ($matches->count() === 1) {
                    if (! $matches->first()->is_active) {
                        throw new DomainException("Curated skin catalog item is inactive: {$name}.");
                    }
                    $matched++;
                    continue;
                }

                DB::table('catalog_items')->insert([
                    'public_id' => (string) Str::uuid(),
                    'category_id' => $categoryId,
                    'canonical_name' => $name,
                    'aliases' => '[]',
                    'provenance' => json_encode([
                        'kind' => 'curated_value_list_manifest',
                        'source_key' => 'google-sheets-skins',
                        'manifest' => 'skin-catalog-v1',
                        'checksum' => $checksum,
                    ], JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            }

            $alreadyAudited = DB::table('audit_events')
                ->where('event_type', 'catalog.skin_manifest.imported')
                ->where('subject_public_id', 'skin-catalog-v1')
                ->exists();
            if (! $alreadyAudited) {
                DB::table('audit_events')->insert([
                    'public_id' => (string) Str::uuid(),
                    'actor_id' => null,
                    'event_type' => 'catalog.skin_manifest.imported',
                    'subject_type' => 'catalog_manifest',
                    'subject_public_id' => 'skin-catalog-v1',
                    'metadata' => json_encode(compact('checksum', 'created', 'matched') + ['total' => count($names)], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }

            return ['created' => $created, 'matched' => $matched, 'total' => count($names)];
        });
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->squish()->ascii()->toString();
    }
}
