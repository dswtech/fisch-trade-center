<?php

namespace App\Domain\Value;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class GoogleSheetsValueSource implements ValueSource
{
    public function fetch(): array
    {
        $url = (string) config('value_source.csv_url');

        if (! config('value_source.enabled') || $url === '') {
            throw new RuntimeException('Production value source is disabled.');
        }

        try {
            $response = Http::accept('text/csv')
                ->connectTimeout((int) config('value_source.connect_timeout_seconds', 5))
                ->timeout((int) config('value_source.timeout_seconds', 15))
                ->withQueryParameters(['tqx' => 'out:csv', 'gid' => (string) config('value_source.gid')])
                ->get($url)
                ->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Production value source could not be reached.', previous: $exception);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Production value source returned an invalid response.', previous: $exception);
        }

        $rows = $this->parse($response->body());
        $lastModified = $response->header('Last-Modified');
        $sourceTimestamp = $lastModified
            ? CarbonImmutable::parse($lastModified)->utc()->toIso8601String()
            : now()->utc()->toIso8601String();

        return [
            'source_timestamp' => $sourceTimestamp,
            'source' => ['key' => 'google-sheets-skins', 'category' => (string) config('value_source.category', 'skin')],
            'rows' => $rows,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function parse(string $csv): array
    {
        if (trim($csv) === '') {
            throw new RuntimeException('Production value source returned an empty document.');
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $headers = fgetcsv($stream, escape: '');

        if (! is_array($headers)) {
            throw new RuntimeException('Production value source has no header row.');
        }

        $headers = array_map(static fn (mixed $header): string => trim((string) $header), $headers);
        $nameHeader = (string) config('value_source.headers.name', 'Name 🎣SKINS');
        $valueHeader = (string) config('value_source.headers.value', 'Value');
        $offerRangeHeader = (string) config('value_source.headers.offer_range', 'Offer Range');

        foreach ([$nameHeader, $valueHeader] as $requiredHeader) {
            if (! in_array($requiredHeader, $headers, true)) {
                throw new RuntimeException('Production value source is missing a required header.');
            }
        }

        $rows = [];
        while (($columns = fgetcsv($stream, escape: '')) !== false) {
            if ($columns === [null] || collect($columns)->every(static fn (mixed $value): bool => trim((string) $value) === '')) {
                continue;
            }
            $columns = array_pad($columns, count($headers), null);
            $row = array_combine($headers, array_slice($columns, 0, count($headers)));
            $name = trim((string) ($row[$nameHeader] ?? ''));
            $rawValue = str_replace([',', ' '], '', trim((string) ($row[$valueHeader] ?? '')));
            $value = filter_var($rawValue, FILTER_VALIDATE_FLOAT);
            if ($value !== false && floor($value) === $value) {
                $value = (int) $value;
            }

            $rows[] = [
                'external_key' => (string) config('value_source.category', 'skin').':'.Str::slug($name),
                'category' => (string) config('value_source.category', 'skin'),
                'name' => $name,
                'value' => $value === false ? $rawValue : $value,
                'offer_range' => in_array($offerRangeHeader, $headers, true) ? trim((string) ($row[$offerRangeHeader] ?? '')) ?: null : null,
            ];
        }
        fclose($stream);

        if ($rows === []) {
            throw new RuntimeException('Production value source contains no data rows.');
        }

        return $rows;
    }
}
