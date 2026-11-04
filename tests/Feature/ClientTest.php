<?php

declare(strict_types=1);

use Beziworld\DaneGov\DaneGovClient;
use Beziworld\DaneGov\Data\Dataset;
use Beziworld\DaneGov\Data\Resource;
use Beziworld\DaneGov\Data\TabularRow;
use Beziworld\DaneGov\Exceptions\ApiException;
use Beziworld\DaneGov\Exceptions\DownloadFailed;
use Beziworld\DaneGov\Exceptions\NotFound;
use Beziworld\DaneGov\Exceptions\ResultWindowExceeded;
use Beziworld\DaneGov\Facades\DaneGov;
use Beziworld\DaneGov\Query;
use Beziworld\DaneGov\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

function api(string $fixture, int $status = 200): \GuzzleHttp\Promise\PromiseInterface
{
    return Http::response(TestCase::fixture($fixture), $status, ['Content-Type' => 'application/vnd.api+json']);
}

/**
 * @return array<string, string>
 */
function params(Request $request): array
{
    $result = [];

    foreach (explode('&', (string) parse_url($request->url(), PHP_URL_QUERY)) as $pair) {
        [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
        $result[urldecode($key)] = urldecode($value);
    }

    return $result;
}

beforeEach(function (): void {
    Sleep::fake();
});

it('lists datasets lazily with version and language headers', function (): void {
    Http::fake(fn(Request $request) => params($request)['page'] === '1' ? api('datasets-search-page1.json') : api('datasets-search-page2.json'));

    $datasets = DaneGov::datasets(Query::make()->search('nazwiska pesel')->perPage(2))->take(3)->all();

    expect($datasets)->toHaveCount(3)
        ->and($datasets[0])->toBeInstanceOf(Dataset::class)
        ->and($datasets[0]->id)->toBe(219);
    Http::assertSentCount(2);
    Http::assertSent(fn(Request $request): bool => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://api.dane.gov.pl/1.4/datasets?')
        && $request->header('X-API-VERSION') === ['1.4']
        && $request->header('Accept-Language') === ['pl']
        && $request->header('Accept') === ['application/vnd.api+json']
        && params($request)['q'] === 'nazwiska pesel'
        && params($request)['per_page'] === '2'
        && params($request)['page'] === '1');
});

it('returns a page with the total and facets', function (): void {
    Http::fake(['api.dane.gov.pl/1.4/datasets*' => api('datasets-search-page1.json')]);

    $page = DaneGov::datasetsPage(Query::make()->search('nazwiska pesel')->facets('by_institution', 'by_format')->perPage(2));

    expect($page->total)->toBe(76)
        ->and($page->items)->toHaveCount(2)
        ->and(collect($page->facet('by_institution'))->firstWhere('id', '333')?->count)->toBe(9)
        ->and($page->hasMorePages())->toBeTrue();
    Http::assertSent(fn(Request $request): bool => params($request)['facet[terms]'] === 'by_institution,by_format');
});

it('fetches a dataset, its resources and showcases', function (): void {
    Http::fake([
        'api.dane.gov.pl/1.4/datasets/568/resources*' => api('dataset-568-resources.json'),
        'api.dane.gov.pl/1.4/datasets/568/showcases*' => api('showcases.json'),
        'api.dane.gov.pl/1.4/datasets/568*' => api('dataset-568.json'),
    ]);

    expect(DaneGov::dataset(568)->title)->toStartWith('Nazwiska występujące')
        ->and(DaneGov::datasetResources(568, Query::make()->perPage(2))->first()?->id)->toBe(54119)
        ->and(DaneGov::datasetShowcases(568)->first()?->title)->toBe('Otoprzychodnie.pl');
});

it('fetches a resource', function (): void {
    Http::fake(['api.dane.gov.pl/1.4/resources/8162*' => api('resource-8162.json')]);

    $resource = DaneGov::resource(8162);

    expect($resource)->toBeInstanceOf(Resource::class)
        ->and($resource->hasTabularData)->toBeTrue();
    Http::assertSent(fn(Request $request): bool => $request->url() === 'https://api.dane.gov.pl/1.4/resources/8162');
});

it('streams tabular rows across pages with filters', function (): void {
    Http::fake(fn(Request $request) => params($request)['page'] === '1' ? api('resource-8162-data-page1.json') : api('resource-8162-data-page2.json'));

    $rows = DaneGov::table(8162)->where('col3', 'KOWALSKI')->whereBetween('col4', 1000, null)->perPage(10)->rows()->all();

    expect($rows)->toHaveCount(15)
        ->and($rows[14])->toBeInstanceOf(TabularRow::class)
        ->and($rows[14]->get('Nazwisko'))->toBe('KOWALSKI')
        ->and($rows[14]->headers['col1'])->toBe('Województwo');
    Http::assertSentCount(2);
    Http::assertSent(fn(Request $request): bool => params($request)['q'] === '(col3:KOWALSKI) AND (col4:[1000 TO *])' && params($request)['per_page'] === '10');
});

it('reads count, columns and aggregations of a table', function (): void {
    Http::fake(['api.dane.gov.pl/1.4/resources/8162/data*' => api('resource-8162-data-aggregations.json')]);

    $reader = DaneGov::table(8162)->where('col1', 'MAZOWIECKIE')->sum('col4')->avg('col4');

    expect($reader->count())->toBe(66684)
        ->and($reader->aggregations()['sum'])->toBe(['col4' => 2429196.0])
        ->and($reader->aggregations()['avg']['col4'])->toEqualWithDelta(36.428, 0.001)
        ->and(array_map(static fn($column): string => $column->label . ':' . $column->type, $reader->columns()))
        ->toBe(['Województwo:string', 'TERYT:integer', 'Nazwisko:string', 'Liczba wystąpień:integer'])
        ->and($reader->first()?->get('col1'))->toBe('MAZOWIECKIE');
    Http::assertSent(fn(Request $request): bool => params($request)['sum'] === 'col4' && params($request)['avg'] === 'col4' && params($request)['per_page'] === '1');
});

it('fetches a single row', function (): void {
    Http::fake(['api.dane.gov.pl/1.4/resources/8162/data/f9bdaec6-c616-5193-a517-d448b9043668' => api('resource-8162-row.json')]);

    $row = DaneGov::row(8162, 'f9bdaec6-c616-5193-a517-d448b9043668');

    expect($row->get('col3'))->toBe('ŽIŠKA')
        ->and($row->rowNumber)->toBe(268444)
        ->and(fn() => DaneGov::row(8162, '../x'))->toThrow(InvalidArgumentException::class);
});

it('stops at the result window of the API', function (): void {
    Http::fake(['api.dane.gov.pl/1.4/resources/8162/data*' => api('resource-8162-data-page1.json')]);

    $count = 0;

    try {
        foreach (DaneGov::table(8162)->perPage(100) as $row) {
            $count++;
        }

        $this->fail('ResultWindowExceeded expected.');
    } catch (ResultWindowExceeded $exception) {
        expect($exception->window)->toBe(10000)
            ->and($exception->total)->toBe(15);
    }

    expect($count)->toBe(1000);
    Http::assertSentCount(100);
});

it('stops early when a limit is set', function (): void {
    Http::fake(['api.dane.gov.pl/1.4/resources/8162/data*' => api('resource-8162-data-page1.json')]);

    expect(DaneGov::table(8162)->perPage(10)->limit(25)->rows()->count())->toBe(25);
    Http::assertSentCount(3);
});

it('lists institutions, their datasets and showcases', function (): void {
    Http::fake([
        'api.dane.gov.pl/1.4/institutions/34/datasets*' => api('institution-34-datasets.json'),
        'api.dane.gov.pl/1.4/institutions/34' => api('institution-34.json'),
        'api.dane.gov.pl/1.4/institutions?*' => api('institutions.json'),
        'api.dane.gov.pl/1.4/showcases?*' => api('showcases.json'),
    ]);

    expect(DaneGov::institutions('statystyczny')->first()?->id)->toBe(34)
        ->and(DaneGov::institution(34)->abbreviation)->toBe('GUS')
        ->and(DaneGov::institutionDatasets(34, Query::make()->perPage(2))->first()?->institutionId)->toBe(34)
        ->and(DaneGov::showcases(Query::make()->perPage(2))->first()?->title)->toBe('Otoprzychodnie.pl');
});

it('searches across models', function (): void {
    Http::fake(['api.dane.gov.pl/1.4/search*' => api('search.json')]);

    $query = Query::make()->search('ludnosc')->where('model', ['dataset', 'resource', 'institution'])->perPage(3);
    $models = DaneGov::search($query)->pluck('model')->all();
    $page = DaneGov::searchPage($query);

    expect($models)->toBe(['resource', 'dataset'])
        ->and($page->aggregations['counters']['datasets'])->toBe(1);
    Http::assertSent(fn(Request $request): bool => params($request)['model[terms]'] === 'dataset,resource,institution');
});

it('maps HTTP 404 to NotFound', function (): void {
    Http::fake(['*' => api('error-404.json', 404)]);

    expect(fn() => DaneGov::resource(99999999))->toThrow(NotFound::class, 'The requested resource could not be found');
});

it('maps validation errors with the offending parameter', function (): void {
    Http::fake(['*' => api('error-422.json', 422)]);

    try {
        DaneGov::get('resources/8162/data', ['per_page' => 101]);
        $this->fail('ApiException expected.');
    } catch (ApiException $exception) {
        expect($exception->isValidationError())->toBeTrue()
            ->and($exception->errorCode)->toBe('422_unprocessable_entity')
            ->and($exception->getMessage())->toContain('Niepoprawny rozmiar strony')->toContain('/per_page')
            ->and($exception->errors)->toHaveCount(1);
    }
});

it('reports the error of a page beyond the result window', function (): void {
    Http::fake(['*' => api('error-400-window.json', 400)]);

    expect(fn() => DaneGov::get('resources/8162/data', ['page' => 101, 'per_page' => 100]))
        ->toThrow(ApiException::class, 'all shards failed');
});

it('retries server errors and connection failures of reads', function (): void {
    Http::fake(['*' => Http::sequence()->push('', 502)->push(TestCase::fixture('institution-34.json'))]);

    expect($this->client(['retries' => 1])->institution(34)->id)->toBe(34);
    Http::assertSentCount(2);
});

it('wraps connection failures after the last retry', function (): void {
    Http::fake(fn() => throw new ConnectionException('cURL error 6: could not resolve host'));

    expect(fn() => $this->client(['retries' => 1])->institution(34))->toThrow(ApiException::class, 'could not resolve host');
});

it('caches responses per language', function (): void {
    Http::fake(['*' => api('institution-34.json')]);
    $client = $this->client(['cache' => ['enabled' => true, 'ttl' => 600]]);

    $client->institution(34);
    $client->institution(34);
    $client->inLanguage('en')->institution(34);

    Http::assertSentCount(2);
    Http::assertSent(fn(Request $request): bool => $request->header('Accept-Language') === ['en']);
});

it('rejects a body that is not JSON', function (): void {
    Http::fake(['*' => Http::response('<html>maintenance</html>', 200)]);

    expect(fn() => DaneGov::institution(34))->toThrow(ApiException::class, 'not valid JSON');
});

it('streams a resource file to disk', function (): void {
    $resource = Resource::fromResource(TestCase::document('resource-8162.json')['data']);
    Http::fake(['api.dane.gov.pl/resources/8162,*' => Http::response("Województwo,TERYT,Nazwisko,Liczba\nMAZOWIECKIE,14,KOWALSKI,12362\n", 200, ['Content-Type' => 'application/octet-stream'])]);
    $directory = TestCase::temporaryDirectory();

    $file = DaneGov::download($resource, $directory);

    expect($file->path)->toBe($directory . DIRECTORY_SEPARATOR . 'Nazwiska-mskie.csv')
        ->and(file_get_contents($file->path))->toContain('KOWALSKI,12362')
        ->and($file->bytes)->toBe(filesize($file->path))
        ->and($file->format)->toBe('csv')
        ->and($file->contentType)->toBe('application/octet-stream')
        ->and(glob($directory . '/*.part'))->toBe([]);
    Http::assertSent(fn(Request $request): bool => $request->url() === 'https://api.dane.gov.pl/resources/8162,nazwiska-meskie-wg-wojewodztw-stan-na-20171231/file');
});

it('downloads a converted format to an explicit path', function (): void {
    $resource = Resource::fromResource(TestCase::document('dataset-568-resources.json')['data'][0]);
    Http::fake(['api.dane.gov.pl/resources/54119,*' => Http::response("a;b\n1;2\n")]);
    $target = TestCase::temporaryDirectory() . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'surnames.csv';

    $file = DaneGov::download($resource, $target, 'CSV');

    expect($file->path)->toBe($target)
        ->and($file->format)->toBe('csv')
        ->and(file_get_contents($target))->toBe("a;b\n1;2\n");
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/csv') || str_contains($request->url(), 'csv'));
});

it('refuses unknown formats and reports failed downloads', function (): void {
    $resource = Resource::fromResource(TestCase::document('resource-8162.json')['data']);
    $directory = TestCase::temporaryDirectory();

    expect(fn() => DaneGov::download($resource, $directory, 'xml'))->toThrow(DownloadFailed::class, 'Available formats: csv');

    Http::fake(['*' => Http::response('gone', 410)]);

    expect(fn() => DaneGov::download($resource, $directory))->toThrow(DownloadFailed::class, 'HTTP 410')
        ->and(glob($directory . '/*'))->toBe([]);
});

it('resolves the client from the container as a singleton', function (): void {
    expect(app(DaneGovClient::class))->toBe(app(DaneGovClient::class))
        ->and(DaneGov::config()->apiVersion)->toBe('1.4');
});
