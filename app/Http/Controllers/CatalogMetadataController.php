<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\CatalogMetadataImportService;
use Illuminate\Http\RedirectResponse;

final class CatalogMetadataController extends Controller
{
    public function store(CatalogMetadataImportService $service): RedirectResponse
    {
        $result = $service->import();

        return back()->with(
            $result['status'] === 'valid' ? 'success' : 'error',
            $result['status'] === 'valid'
                ? 'Catalog metadata staged for review.'
                : 'Catalog metadata source is unavailable; catalog and values are unchanged.'
        );
    }
}
