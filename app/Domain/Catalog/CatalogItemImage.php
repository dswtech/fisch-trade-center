<?php

namespace App\Domain\Catalog;

use Illuminate\Support\Facades\Storage;

final class CatalogItemImage
{
    public static function url(?string $path, string $category): string
    {
        if ($path) {
            return Storage::disk((string) config('catalog.media_disk', 'public'))->url($path);
        }

        $fallback = array_key_exists($category, (array) config('catalog.categories')) ? $category : 'other';

        return asset("images/categories/{$fallback}.svg");
    }
}
