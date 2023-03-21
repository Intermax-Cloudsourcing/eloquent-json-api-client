<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Intermax\EloquentJsonApiClient\Tests\Team;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

it('fetches and hydrates a team', function () {
    Http::fake(fn () => Http::response(file_get_contents(__DIR__.'/Utilities/TeamFindResponse.json')));

    $team = Team::find(1);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'teams/1'));

    expect($team)->toBeInstanceOf(Team::class)
        ->and($team->name)->toBe('Development')
        ->and($team->createdAt)->toBeInstanceOf(Carbon\Carbon::class);
});

it('fetches and hydrates a team with its relations', function () {
    Http::fake(fn () => Http::response(file_get_contents(__DIR__.'/Utilities/TeamFindRelationResponse.json')));

    $team = Team::query()->with(['members'])->find(1);

    assert($team instanceof Team);

    Http::assertSent(
        fn (Request $request) => str_contains($request->url(), 'teams/1') && str_contains($request->url(), 'include=members')
    );

    expect($team)->toBeInstanceOf(Team::class)
        ->and($team->members)->toBeInstanceOf(Collection::class)
        ->and($team->members->first()->id)->toBe('5');
});

it('sends headers with the request', function () {
    Http::fake(fn () => Http::response(file_get_contents(__DIR__.'/Utilities/TeamFindResponse.json')));

    Team::find(1);

    Http::assertSent(
        fn (Request $request) => Arr::first($request->header('api-key')) === 'test'
    );
});
