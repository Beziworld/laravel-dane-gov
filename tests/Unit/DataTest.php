<?php

declare(strict_types=1);

use Beziworld\DaneGov\Data\Dataset;
use Beziworld\DaneGov\Data\Institution;
use Beziworld\DaneGov\Data\Page;
use Beziworld\DaneGov\Data\Resource;
use Beziworld\DaneGov\Data\SearchResult;
use Beziworld\DaneGov\Data\Showcase;
use Beziworld\DaneGov\Data\TabularRow;
use Beziworld\DaneGov\Tests\TestCase;

it('maps a dataset', function (): void {
    $dataset = Dataset::fromResource(TestCase::document('dataset-568.json')['data']);

    expect($dataset->id)->toBe(568)
        ->and($dataset->slug)->toBe('nazwiska-wystepujace-w-rejestrze-pesel')
        ->and($dataset->title)->toBe('Nazwiska występujące w rejestrze PESEL z uwzględnieniem nazwisk osób zmarłych')
        ->and($dataset->institutionId)->toBe(333)
        ->and($dataset->resourcesCount)->toBe(26)
        ->and($dataset->showcasesCount)->toBe(1)
        ->and($dataset->formats)->toBe(['csv', 'jsonld', 'xlsx'])
        ->and($dataset->opennessScores)->toBe([2, 3, 4])
        ->and($dataset->licenseName)->toBe('CC0 1.0')
        ->and($dataset->updateFrequency)->toBe('Rocznie')
        ->and($dataset->categories[0]->code)->toBe('SOCI')
        ->and($dataset->category?->title)->toBe('Społeczeństwo')
        ->and($dataset->regions)->toBe(['Polska'])
        ->and($dataset->hasHighValueData)->toBeFalse()
        ->and($dataset->url)->toBeNull()
        ->and($dataset->created?->format('Y-m-d H:i T'))->toBe('2018-02-12 15:05 CET')
        ->and($dataset->plainNotes())->toStartWith('Wykaz nazwisk występujących w rejestrze PESEL.')
        ->and($dataset->portalUrl())->toBe('https://dane.gov.pl/pl/dataset/568,nazwiska-wystepujace-w-rejestrze-pesel');
});

it('strips search highlighting from titles', function (): void {
    $institution = Institution::fromResource(TestCase::document('institutions.json')['data'][0]);

    expect($institution->title)->toBe('Główny Urząd Statystyczny');
});

it('maps a tabular resource with its files', function (): void {
    $resource = Resource::fromResource(TestCase::document('resource-8162.json')['data']);

    expect($resource->id)->toBe(8162)
        ->and($resource->title)->toBe('Nazwiska męskie wg województw - stan na 2017-12-31')
        ->and($resource->format)->toBe('csv')
        ->and($resource->mediaType)->toBe('file')
        ->and($resource->datasetId)->toBe(568)
        ->and($resource->institutionId)->toBe(333)
        ->and($resource->hasTabularData)->toBeTrue()
        ->and($resource->fileSize)->toBe(18609520)
        ->and($resource->availableFormats())->toBe(['csv'])
        ->and($resource->file('CSV')?->downloadUrl)->toBe('https://api.dane.gov.pl/resources/8162,nazwiska-meskie-wg-wojewodztw-stan-na-20171231/file')
        ->and($resource->dataDate?->toDateString())->toBe('2017-12-31')
        ->and($resource->containsProtectedData)->toBeFalse();
});

it('lists the converted files of a resource', function (): void {
    $resource = Resource::fromResource(TestCase::document('dataset-568-resources.json')['data'][0]);

    expect($resource->id)->toBe(54119)
        ->and($resource->format)->toBe('xlsx')
        ->and($resource->availableFormats())->toContain('xlsx', 'csv')
        ->and($resource->csvDownloadUrl)->toBe('https://api.dane.gov.pl/resources/54119,nazwiska-meskie-stan-na-2024-01-19/csv');
});

it('maps an institution with its address', function (): void {
    $institution = Institution::fromResource(TestCase::document('institution-34.json')['data']);

    expect($institution->abbreviation)->toBe('GUS')
        ->and($institution->regon)->toBe('000331501')
        ->and($institution->type)->toBe('state')
        ->and($institution->address())->toBe('al. Niepodległości 208, 00-925 Warszawa')
        ->and($institution->datasetsCount)->toBe(109)
        ->and($institution->resourcesCount)->toBe(151)
        ->and($institution->created?->toDateString())->toBe('2015-06-22');
});

it('maps showcases and search hits', function (): void {
    $showcase = Showcase::fromResource(TestCase::document('showcases.json')['data'][0]);
    $hits = array_map(SearchResult::fromResource(...), TestCase::document('search.json')['data']);

    expect($showcase->title)->toBe('Otoprzychodnie.pl')
        ->and($showcase->categoryName)->toBe('Serwis WWW')
        ->and($showcase->datasetsCount)->toBe(3)
        ->and($hits[0]->model)->toBe('resource')
        ->and($hits[0]->title)->toBe('ludnosc-prognoza-2017-2030.csv')
        ->and($hits[0]->datasetId)->not->toBeNull()
        ->and($hits[1]->model)->toBe('dataset');
});

it('reads tabular rows by column name or header label', function (): void {
    $document = TestCase::document('resource-8162-data-page1.json');
    $row = TabularRow::fromResource($document['data'][0], 8162, $document['meta']['headers_map']);

    expect(array_keys($row->values))->toBe(['col1', 'col2', 'col3', 'col4'])
        ->and($row->get('col3'))->toBe('KOWALSKI')
        ->and($row->get('Nazwisko'))->toBe('KOWALSKI')
        ->and($row->text('col4'))->toBeString()
        ->and($row->get('Liczba wystąpień'))->toBeFloat()
        ->and(array_keys($row->toNamedArray()))->toBe(['Województwo', 'TERYT', 'Nazwisko', 'Liczba wystąpień'])
        ->and($row->rowNumber)->toBeInt()
        ->and($row->resourceId)->toBe(8162);
});

it('splits facets from other aggregations in a page', function (): void {
    $page = Page::fromDocument(TestCase::document('datasets-search-page1.json'), [], 1, 2);
    $search = Page::fromDocument(TestCase::document('search.json'), [], 1, 3);

    expect($page->total)->toBe(76)
        ->and($page->lastPage())->toBe(38)
        ->and($page->hasMorePages())->toBeTrue()
        ->and($page->facet('by_institution'))->not->toBeEmpty()
        ->and($page->facet('by_format'))->not->toBeEmpty()
        ->and($page->facet('by_institution')[0]->count)->toBeGreaterThan(0)
        ->and($search->aggregations['counters'])->toMatchArray(['datasets' => 1, 'resources' => 1])
        ->and($search->hasMorePages())->toBeFalse();
});
