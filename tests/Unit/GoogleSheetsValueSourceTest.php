<?php

namespace Tests\Unit;

use App\Domain\Value\GoogleSheetsValueSource;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class GoogleSheetsValueSourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('value_source.enabled', true);
        config()->set('value_source.csv_url', 'https://example.test/values.csv');
        config()->set('value_source.gid', '1079603703');
        config()->set('value_source.category', 'skin');
        config()->set('value_source.headers.name', 'Name');
        config()->set('value_source.headers.value', 'Value');
        config()->set('value_source.headers.offer_range', 'Offer Range');
    }

    public function test_it_normalizes_csv_rows_and_optional_offer_range(): void
    {
        Http::fake(['example.test/*' => Http::response("Name,Value,Offer Range\nAbyssal Current,2 400,2300-2500\nTide Skin,7.5,7.5-9\n", 200, ['Last-Modified' => 'Sat, 24 Oct 2026 00:00:00 GMT'])]);

        $payload = (new GoogleSheetsValueSource)->fetch();

        self::assertSame('google-sheets-skins', $payload['source']['key']);
        self::assertSame(2400, $payload['rows'][0]['value']);
        self::assertSame('2300-2500', $payload['rows'][0]['offer_range']);
        self::assertSame(7.5, $payload['rows'][1]['value']);
        self::assertSame('7.5-9', $payload['rows'][1]['offer_range']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'gid=1079603703') && str_contains($request->url(), 'tqx=out%3Acsv'));
    }

    public function test_it_fails_closed_for_missing_headers_empty_documents_and_http_errors(): void
    {
        foreach ([
            Http::response("Wrong,Value\nA,1", 200),
            Http::response('', 200),
            Http::response('unavailable', 503),
        ] as $response) {
            Http::fake(['example.test/*' => $response]);
            try {
                (new GoogleSheetsValueSource)->fetch();
                self::fail('Expected the source to fail closed.');
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }
    }
}
