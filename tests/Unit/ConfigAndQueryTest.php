<?php

declare(strict_types=1);

use Beziworld\DaneGov\DaneGovConfig;
use Beziworld\DaneGov\Exceptions\InvalidConfiguration;
use Beziworld\DaneGov\Query;

it('reads defaults from an empty configuration', function (): void {
    $config = DaneGovConfig::fromArray([]);

    expect($config->baseUrl)->toBe('https://api.dane.gov.pl')
        ->and($config->apiVersion)->toBe('1.4')
        ->and($config->language)->toBe('pl')
        ->and($config->perPage)->toBe(100)
        ->and($config->cachesResponses())->toBeTrue()
        ->and($config->withLanguage('EN')->language)->toBe('en');
});

it('treats a zero TTL as disabled caching', function (): void {
    expect(DaneGovConfig::fromArray(['cache' => ['ttl' => 0]])->cachesResponses())->toBeFalse()
        ->and(DaneGovConfig::fromArray(['cache' => ['enabled' => 'false']])->cachesResponses())->toBeFalse();
});

it('rejects invalid configuration values', function (array $config, string $message): void {
    expect(fn() => DaneGovConfig::fromArray($config))->toThrow(InvalidConfiguration::class, $message);
})->with([
    'language' => [['language' => 'de'], 'DANE_GOV_LANG'],
    'per page' => [['per_page' => 250], 'at most 100'],
    'version' => [['api_version' => 'latest'], 'DANE_GOV_API_VERSION'],
    'url' => [['base_url' => 'nope'], 'DANE_GOV_URL'],
]);

it('builds filters in the bracket syntax of the API', function (): void {
    $query = Query::make()
        ->search('ludność')
        ->where('institution.id', 34)
        ->where('openness_score', 3, 'gte')
        ->where('format', ['csv', 'xlsx'])
        ->where('has_high_value_data', true)
        ->sort('-modified', 'title')
        ->facets('by_institution', 'by_format')
        ->include('institution')
        ->perPage(50);

    expect($query->toArray())->toBe([
        'q' => 'ludność',
        'institution[id][term]' => '34',
        'openness_score[gte]' => '3',
        'format[terms]' => 'csv,xlsx',
        'has_high_value_data[term]' => 'true',
        'sort' => '-modified,title',
        'facet[terms]' => 'by_institution,by_format',
        'include' => 'institution',
        'per_page' => 50,
    ]);
});

it('is immutable and converts from phrases and arrays', function (): void {
    $base = Query::make()->search('a');
    $other = $base->perPage(10);

    expect($base->get('per_page'))->toBeNull()
        ->and($other->get('per_page'))->toBe(10)
        ->and($other->without('per_page')->toArray())->toBe(['q' => 'a'])
        ->and(Query::from('pesel')->toArray())->toBe(['q' => 'pesel'])
        ->and(Query::from(['q' => 'x', 'sort' => 'id'])->toArray())->toBe(['q' => 'x', 'sort' => 'id'])
        ->and(Query::from(null)->toArray())->toBe([])
        ->and(Query::from('  ')->toArray())->toBe([]);
});

it('validates lookups, field names and page sizes', function (): void {
    expect(fn() => Query::make()->where('title', 'x', 'like'))->toThrow(InvalidArgumentException::class, 'Unknown lookup')
        ->and(fn() => Query::make()->where('title]', 'x'))->toThrow(InvalidArgumentException::class, 'not a valid field')
        ->and(fn() => Query::make()->perPage(101))->toThrow(InvalidArgumentException::class);
});
