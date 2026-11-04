# Laravel dane.gov.pl

[![tests](https://github.com/beziworld/laravel-dane-gov/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-dane-gov/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-dane-gov.svg)](https://packagist.org/packages/beziworld/laravel-dane-gov)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-dane-gov.svg)](LICENSE.md)

Laravel client for the [API of dane.gov.pl](https://api.dane.gov.pl/doc), the Polish open data portal: tens of
thousands of datasets published by ministries, agencies, voivodeships and municipalities.

- datasets with full-text search, filters, sorting and facets (`by_institution`, `by_format`, ...)
- resources (files, links, APIs) with their converted files (CSV, JSON-LD) and metadata
- institutions (publishers), showcases (applications and websites built on the data) and cross-model search
- typed DTOs: `Dataset`, `Resource`, `Institution`, `Showcase`, `SearchResult`, `TabularRow`, `Page`
- lazy iterators (`LazyCollection`): further pages are requested only while you iterate
- tabular data reader that streams the rows of a resource with filters, ranges, sorting, sums and averages
- download helper that streams resource files to disk
- response cache, API version and language selection, `dane:search` and `dane:resource` Artisan commands

[Polska wersja dokumentacji](README.pl.md)

## Why this package

At the time of writing Packagist has no package for the dane.gov.pl API (searches for "dane.gov.pl", "danegov" and
"otwarte dane" return nothing) and GitHub has clients in Python, Go and R only. This package adds a typed Laravel
client with lazy paging, a query builder for the filter syntax and a reader for tabular data.

## Installation

```bash
composer require beziworld/laravel-dane-gov
php artisan vendor:publish --tag=dane-gov-config   # optional
```

No key and no registration are needed. Optional `.env` settings:

```dotenv
DANE_GOV_API_VERSION=1.4      # sent as X-API-VERSION and as the path prefix
DANE_GOV_LANG=pl              # pl or en (Accept-Language)
DANE_GOV_PER_PAGE=100         # 1-100, records per page of lazy iterators
DANE_GOV_TIMEOUT=30
DANE_GOV_RETRIES=2            # connection errors and HTTP 5xx of reads
DANE_GOV_CACHE=true
DANE_GOV_CACHE_STORE=
DANE_GOV_CACHE_TTL=3600       # seconds; 0 disables caching
DANE_GOV_DOWNLOAD_TIMEOUT=600
DANE_GOV_DOWNLOAD_DIRECTORY=  # default storage/app/dane-gov
```

## Datasets

```php
use Beziworld\DaneGov\Facades\DaneGov;
use Beziworld\DaneGov\Query;

foreach (DaneGov::datasets('jakość powietrza') as $dataset) {   // all pages, lazily
    $dataset->title;            // highlighting removed
    $dataset->institutionId;
    $dataset->formats;          // ["csv", "xlsx"]
    $dataset->modified;         // CarbonImmutable, Europe/Warsaw
}

$query = Query::make()
    ->search('ludność')
    ->where('institution.id', 34)              // institution[id][term]=34
    ->where('format', ['csv', 'xlsx'])         // format[terms]=csv,xlsx
    ->where('openness_score', 3, 'gte')        // openness_score[gte]=3
    ->where('has_high_value_data', true)
    ->sort('-modified')
    ->facets('by_institution', 'by_format');

$page = DaneGov::datasetsPage($query);         // one page with the total and facets
$page->total;                                  // number of matches
$page->facet('by_institution')[0]->label();    // institution name
$page->facet('by_institution')[0]->count;

$dataset = DaneGov::dataset(568);
$dataset->resourcesCount;                      // 26
$dataset->portalUrl();                         // https://dane.gov.pl/pl/dataset/568,...
DaneGov::datasetResources(568)->all();
DaneGov::datasetShowcases(568)->all();
```

## Resources, institutions, showcases and search

```php
$resource = DaneGov::resource(8162);
$resource->format;              // "csv"
$resource->hasTabularData;      // true: rows available through table()
$resource->availableFormats();  // ["csv"] or ["xlsx", "csv", "jsonld"]
$resource->datasetId;           // 568

DaneGov::resources(Query::make()->search('budżet')->where('format', 'csv'))->take(10)->all();

$gus = DaneGov::institution(34);
$gus->abbreviation;             // "GUS"
$gus->address();                // "al. Niepodległości 208, 00-925 Warszawa"
DaneGov::institutionDatasets(34)->take(20)->all();
DaneGov::institutions('statystyczny')->all();

DaneGov::showcases()->take(5)->pluck('title');

$hits = DaneGov::search(Query::make()->search('powietrze')->where('model', ['dataset', 'resource']));
DaneGov::searchPage('powietrze')->aggregations['counters'];   // ["datasets" => ..., "resources" => ...]

DaneGov::inLanguage('en')->dataset(568);
DaneGov::get('dga-aggregated');   // raw JSON:API document of any other endpoint
```

## Tabular data

Files imported by the portal (CSV, XLSX and similar) are available row by row. Columns have technical names
(`col1`, `col2`, ...) and header labels.

```php
$table = DaneGov::table(8162)                 // "Nazwiska męskie wg województw"
    ->where('col3', 'NOWAK')                  // exact value, escaped and quoted when needed
    ->whereBetween('col4', 1000, null)        // col4:[1000 TO *]
    ->whereIn('col1', ['MAZOWIECKIE', 'ŚLĄSKIE'])
    ->query('col3:NOWAK*')                    // raw query string syntax: wildcards, OR, fuzzy, regex
    ->sort('-col4');

$table->count();                              // matching rows, one small request
$table->columns();                            // list<Column>: name "col3", label "Nazwisko", type "string"

foreach ($table->rows() as $row) {            // streamed page by page
    $row->get('Nazwisko');                    // by label or by column name
    $row->text('col4');                       // textual representation
    $row->toNamedArray();                     // ["Województwo" => ..., "Nazwisko" => ..., ...]
}

DaneGov::table(8162)->where('col3', 'NOWAK')->sum('col4')->avg('col4')->aggregations();
// ["sum" => ["col4" => 93018.0], "avg" => ["col4" => ...]]

DaneGov::table(8162)->limit(500)->rows();     // stop after 500 rows
DaneGov::row(8162, 'f9bdaec6-c616-5193-a517-d448b9043668');
```

The API pages through the first 10 000 records of any result only (deeper pages fail with HTTP 400). The reader
throws `ResultWindowExceeded` before requesting such a page; narrow the query with filters, use `limit()`, or
download the file to process every row.

## Downloads

```php
$file = DaneGov::download(8162);                          // to storage/app/dane-gov/<file name>
$file = DaneGov::download(54119, storage_path('imports/'), 'csv');  // converted CSV of an XLSX resource
$file = DaneGov::download($resource, '/tmp/surnames.csv');

$file->path;    $file->bytes;    $file->contentType;    $file->url;
```

Files are streamed to disk (no memory buffering), written to a `.part` file first and renamed when complete.
Redirects to the media server are followed; only http and https URLs are accepted.

## Artisan

```bash
php artisan dane:search "jakość powietrza" --facets
php artisan dane:search budżet --resources --format=csv --institution=34 --sort=-modified --limit=50
php artisan dane:resource 8162 --rows=20 --where=col1=MAZOWIECKIE --sort=-col4
php artisan dane:resource 8162 --q="col3:KOWAL*" --rows=5
php artisan dane:resource 54119 --rows=0 --download=storage/app/imports/ --format=csv
```

## Errors

| Situation | Result |
|-----------|--------|
| HTTP 404 (unknown dataset, resource, institution, row) | `NotFound` |
| HTTP 422 (invalid parameter, for example per_page above 100) | `ApiException` (`isValidationError()`, `errors`, pointer in the message) |
| HTTP 400, HTTP 5xx after retries | `ApiException` (`httpStatus`, `errorCode`, `isServerError()`) |
| Connection failure or timeout after retries | `ApiException` wrapping the `ConnectionException` |
| Iteration beyond the first 10 000 records | `ResultWindowExceeded` (`total`, `window`) |
| No file, unknown format, HTTP error or disk error while downloading | `DownloadFailed` |
| Invalid configuration | `InvalidConfiguration` |
| Invalid field name, lookup, column, page size | `InvalidArgumentException` |

All package exceptions extend `Beziworld\DaneGov\Exceptions\DaneGovException`.

## Development

```bash
composer install
composer ci   # pint --test, phpstan (level 8), pest
```

Tests use responses captured from the live API (`tests/Fixtures`); no request leaves the test suite.

## Author

Created and maintained by **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Issues: [GitHub issues](https://github.com/beziworld/laravel-dane-gov/issues)

Need an integration? Get in touch through [beziworld.eu](https://beziworld.eu).

### Other packages by the author

[laravel-gus-bdl](https://github.com/beziworld/laravel-gus-bdl) ·
[laravel-teryt](https://github.com/beziworld/laravel-teryt) ·
[laravel-krs](https://github.com/beziworld/laravel-krs) ·
[laravel-nbp](https://github.com/beziworld/laravel-nbp)

## License

MIT. See [LICENSE.md](LICENSE.md). Data published on dane.gov.pl is made available by its providers under the terms
shown with each dataset (license and conditions of re-use). This package is not affiliated with or endorsed by the
Ministry of Digital Affairs or the operators of dane.gov.pl.
