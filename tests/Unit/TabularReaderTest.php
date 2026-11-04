<?php

declare(strict_types=1);

use Beziworld\DaneGov\DaneGovClient;
use Beziworld\DaneGov\TabularReader;

it('escapes values for the query string syntax', function (): void {
    expect(TabularReader::term('MAZOWIECKIE'))->toBe('MAZOWIECKIE')
        ->and(TabularReader::term('NOWAK-KOWALSKA'))->toBe('NOWAK\\-KOWALSKA')
        ->and(TabularReader::term('a:b/c'))->toBe('a\\:b\\/c')
        ->and(TabularReader::term('beskidzki "dworzec"'))->toBe('"beskidzki \\"dworzec\\""')
        ->and(TabularReader::term(1000))->toBe('1000')
        ->and(TabularReader::term(true))->toBe('true');
});

it('combines conditions with AND and keeps sorting and aggregations', function (): void {
    $reader = app(DaneGovClient::class)->table(8162)
        ->where('col1', 'MAZOWIECKIE')
        ->whereBetween('col4', 1000, null)
        ->whereIn('col3', ['KOWALSKI', 'NOWAK'])
        ->sort('-col4')
        ->sum('col4')
        ->avg('col4');

    expect($reader->toQueryString())->toBe('(col1:MAZOWIECKIE) AND (col4:[1000 TO *]) AND ((col3:KOWALSKI OR col3:NOWAK))')
        ->and($reader->params())->toBe([
            'q' => '(col1:MAZOWIECKIE) AND (col4:[1000 TO *]) AND ((col3:KOWALSKI OR col3:NOWAK))',
            'sort' => '-col4',
            'sum' => 'col4',
            'avg' => 'col4',
        ]);
});

it('keeps a single condition unwrapped and rejects invalid input', function (): void {
    $reader = app(DaneGovClient::class)->table(8162);

    expect($reader->query('col3:KOWAL*')->toQueryString())->toBe('col3:KOWAL*')
        ->and($reader->toQueryString())->toBeNull()
        ->and($reader->query('  ')->params())->toBe([])
        ->and(fn() => $reader->where('col 1', 'x'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => $reader->whereIn('col1', []))->toThrow(InvalidArgumentException::class)
        ->and(fn() => $reader->perPage(0))->toThrow(InvalidArgumentException::class);
});
