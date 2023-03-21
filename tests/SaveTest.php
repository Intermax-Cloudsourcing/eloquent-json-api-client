<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Intermax\EloquentJsonApiClient\Tests\Team;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

it('creates a team', function () {
    Http::fake(fn () => Http::response(file_get_contents(__DIR__.'/Utilities/TeamGetResponse.json')));

    $teams = Team::query()->get();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'teams'));

    expect($teams)->toBeInstanceOf(Collection::class)
        ->and($teams->count())->toBe(4)
        ->and($teams->first())->toBeInstanceOf(Team::class);
});

it('updates a team', function () {
    Http::fake(fn () => Http::response([
        'data' => [
            'id' => '1',
            'type' => 'teams',
            'attributes' => [
                'externalId' => '1',
                'name' => 'Security',
                'createdAt' => $createdAt = Carbon::now()->subHour()->toISOString(),
                'updatedAt' => $updatedAt = Carbon::now()->toISOString(),
            ],
        ],
    ]));

    $team = new Team();
    $team->exists = true; // Usually set by hydrating
    $team->id = '1';
    $team->externalId = '1';
    $team->name = 'Security';
    $team->save();

    Http::assertSent(
        fn (Request $request) => dd($request->body())
    );

    expect($team->createdAt)->not()->toBeNull()
        ->and($team->updatedAt)->not()->toBeNull();
});
