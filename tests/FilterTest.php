<?php

declare(strict_types=1);

use Intermax\EloquentJsonApiClient\Tests\Car;

it('produces a json api query with a filter', function () {
    $queryString = Car::query()->where('color', 'blue')->toQuery();

    expect($queryString)->toBe('/cars?'.urlencode('filter[color][eq]').'=blue');
});

it('produces a json api query with multiple filters', function () {
    $queryString = Car::query()
        ->where('color', 'blue')
        ->where('brand', 'ferrari')
        ->toQuery();

    expect($queryString)
        ->toContain('/cars?')
        ->toContain(urlencode('filter[color][eq]').'=blue')
        ->toContain(urlencode('filter[brand][eq]').'=ferrari');
});

it('produces a json api query with a contains filter', function () {
    $queryString = Car::query()
        ->where('brand', 'like', '%ferr%')
        ->toQuery();

    expect($queryString)
        ->toContain(urlencode('filter[brand][contains]').'=ferr');
});
