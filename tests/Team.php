<?php

declare(strict_types=1);

namespace Intermax\EloquentJsonApiClient\Tests;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Intermax\EloquentJsonApiClient\Attributes\DateTime;
use Intermax\EloquentJsonApiClient\Attributes\Property;
use Intermax\EloquentJsonApiClient\Attributes\ReadOnlyProperty;
use Intermax\EloquentJsonApiClient\Attributes\Relationship;
use Intermax\EloquentJsonApiClient\Model;
use Intermax\EloquentJsonApiClient\RelationType;

class Team extends Model
{
    #[Property]
    public ?string $externalId;

    #[Property]
    public string $name;

    #[Property]
    #[ReadOnlyProperty]
    #[DateTime]
    public ?Carbon $createdAt;

    #[Property]
    #[ReadOnlyProperty]
    #[DateTime]
    public ?Carbon $updatedAt;

    #[Relationship(User::class, RelationType::Many)]
    public Collection $members;

    public function baseUrl(): string
    {
        return 'https://localhost';
    }

    public function headers(): array
    {
        return ['api-key' => 'test'];
    }
}
