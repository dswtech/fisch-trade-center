<?php

namespace App\Domain\Catalog;

interface CatalogMetadataSource
{
    /** @return array{source_timestamp:?string, rows:list<array<string, mixed>>, metadata:array<string, mixed>} */
    public function fetch(): array;
}
