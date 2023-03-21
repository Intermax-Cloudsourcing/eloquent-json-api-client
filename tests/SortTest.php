<?php

declare(strict_types=1);

use Intermax\EloquentJsonApiClient\Tests\Team;

it('applies a sort parameter to the query', function () {
    $queryString = Team::query()
        ->orderBy('name')
        ->orderBy('id', 'desc')->toQuery();

    expect($queryString)
        ->toContain('/teams?')
        ->toContain('sort='.urlencode('name,-id'));
});
