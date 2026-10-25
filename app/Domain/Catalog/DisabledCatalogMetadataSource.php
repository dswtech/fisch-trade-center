<?php

namespace App\Domain\Catalog;

use RuntimeException;

final class DisabledCatalogMetadataSource implements CatalogMetadataSource
{
    public function fetch(): array
    {
        throw new RuntimeException('Catalog metadata source is disabled.');
    }
}
