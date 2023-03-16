<?php

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
