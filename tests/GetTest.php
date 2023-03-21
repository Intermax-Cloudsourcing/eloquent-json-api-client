<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Intermax\EloquentJsonApiClient\Tests\Team;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

it('fetches teams', function () {
    Http::fake(fn () => Http::response(file_get_contents(__DIR__.'/Utilities/TeamGetResponse.json'))
    );

    $teams = Team::query()->get();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'teams'));

    expect($teams)->toBeInstanceOf(Collection::class)
        ->and($teams->count())->toBe(4)
        ->and($teams->first())->toBeInstanceOf(Team::class);
});
