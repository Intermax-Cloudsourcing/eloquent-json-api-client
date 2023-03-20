<?php

use Illuminate\Http\Client\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Intermax\EloquentJsonApiClient\Tests\Team;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

it('paginates teams', function () {
    Http::fake(fn () =>
        Http::response(file_get_contents(__DIR__.'/Utilities/TeamPaginateResponse.json'))
    );

    $teams = Team::query()->paginate();

    Http::assertSent(
        fn (Request $request) =>
            str_contains($request->url(), 'teams')
            && str_contains($request->url(), urlencode('page[number]').'=1')
    );

    expect($teams)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($teams->total())->toBe(8)
        ->and($teams->lastPage())->toBe(2)
        ->and($teams->count())->toBe(4)
        ->and($teams->first())->toBeInstanceOf(Team::class);
});
