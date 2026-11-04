<?php

declare(strict_types=1);

use Beziworld\DaneGov\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function daneFixture(string $name, int $status = 200): \GuzzleHttp\Promise\PromiseInterface
{
    return Http::response(TestCase::fixture($name), $status, ['Content-Type' => 'application/vnd.api+json']);
}

it('searches datasets and prints facets', function (): void {
    Http::fake(['api.dane.gov.pl/1.4/datasets*' => daneFixture('datasets-search-page1.json')]);

    $this->artisan('dane:search', ['query' => 'nazwiska pesel', '--facets' => true, '--limit' => 2, '--sort' => '-modified'])
        ->expectsOutputToContain('Główny Urząd Statystyczny (1)')
        ->expectsOutputToContain('76 match(es) in total.')
        ->assertSuccessful();

    Http::assertSent(fn(Request $request): bool => str_contains(urldecode($request->url()), 'sort=-modified') && str_contains(urldecode($request->url()), 'per_page=2'));
});

it('searches resources with an institution filter', function (): void {
    Http::fake(['api.dane.gov.pl/1.4/resources*' => daneFixture('dataset-568-resources.json')]);

    $this->artisan('dane:search', ['query' => 'nazwiska', '--resources' => true, '--institution' => '333'])
        ->expectsOutputToContain('54119')
        ->assertSuccessful();

    Http::assertSent(fn(Request $request): bool => str_contains(urldecode($request->url()), 'institution[id][term]=333'));
});

it('shows a resource and previews its rows', function (): void {
    Http::fake([
        'api.dane.gov.pl/1.4/resources/8162/data*' => daneFixture('resource-8162-data-page1.json'),
        'api.dane.gov.pl/1.4/resources/8162' => daneFixture('resource-8162.json'),
    ]);

    $this->artisan('dane:resource', ['id' => '8162', '--rows' => 3, '--where' => ['col3=KOWALSKI'], '--sort' => '-col4'])
        ->expectsOutputToContain('Nazwiska męskie wg województw')
        ->expectsOutputToContain('Nazwisko (col3)')
        ->expectsOutputToContain('15 matching row(s).')
        ->assertSuccessful();

    Http::assertSent(fn(Request $request): bool => str_contains(urldecode($request->url()), 'q=col3:KOWALSKI'));
});

it('downloads the file of a resource', function (): void {
    Http::fake([
        'api.dane.gov.pl/1.4/resources/8162' => daneFixture('resource-8162.json'),
        'api.dane.gov.pl/resources/8162,*' => Http::response("a,b\n"),
    ]);
    $directory = TestCase::temporaryDirectory();

    $this->artisan('dane:resource', ['id' => '8162', '--rows' => 0, '--download' => $directory])
        ->expectsOutputToContain('Saved')
        ->assertSuccessful();

    expect(file_get_contents($directory . DIRECTORY_SEPARATOR . 'Nazwiska-mskie.csv'))->toBe("a,b\n");
});

it('fails on API errors and invalid ids', function (): void {
    Http::fake(['*' => daneFixture('error-404.json', 404)]);

    $this->artisan('dane:resource', ['id' => '99999999'])->assertFailed();
    $this->artisan('dane:resource', ['id' => 'abc'])->assertExitCode(2);
});
