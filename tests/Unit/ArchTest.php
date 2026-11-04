<?php

declare(strict_types=1);

arch('every source file declares strict types')
    ->expect('Beziworld\DaneGov')
    ->toUseStrictTypes();

arch('no debugging statements are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('data objects are immutable')
    ->expect('Beziworld\DaneGov\Data')
    ->toBeReadonly();

arch('exceptions share the package base exception')
    ->expect('Beziworld\DaneGov\Exceptions')
    ->toExtend('Beziworld\DaneGov\Exceptions\DaneGovException')
    ->ignoring('Beziworld\DaneGov\Exceptions\DaneGovException');
