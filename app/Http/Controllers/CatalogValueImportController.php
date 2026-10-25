<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\CatalogValueCsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CatalogValueImportController extends Controller
{
    public function template(CatalogValueCsvImportService $service): StreamedResponse
    {
        return response()->streamDownload(function () use ($service): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['item_key', 'category', 'name', 'aliases', 'value', 'offer_range', 'status']);
            foreach ($service->templateRows() as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, 'catalog-value-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request, CatalogValueCsvImportService $service): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $result = $service->stage($data['file'], $request->user());

        return back()->with(
            $result['status'] === 'valid' ? 'success' : 'error',
            $result['status'] === 'valid' ? 'CSV staged. Review the preview before publishing.' : 'CSV rejected. Review validation errors.'
        );
    }

    public function publish(Request $request, string $import, CatalogValueCsvImportService $service): RedirectResponse
    {
        $data = $request->validate(['confirmWarnings' => ['nullable', 'boolean']]);
        $result = $service->publish($import, $request->user(), (bool) ($data['confirmWarnings'] ?? false));

        return back()->with('success', $result['already_published'] ? 'Import already published.' : 'Catalog and immutable value snapshot published.');
    }
}
