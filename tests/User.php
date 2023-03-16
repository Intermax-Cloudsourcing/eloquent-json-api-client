<?php

declare(strict_types=1);

namespace Intermax\EloquentJsonApiClient\Tests;

use Carbon\Carbon;
use Intermax\EloquentJsonApiClient\Attributes\DateTime;
use Intermax\EloquentJsonApiClient\Attributes\Property;
use Intermax\EloquentJsonApiClient\Model;

class User extends Model
{
    #[Property]
    public string $id;

    #[Property]
    public string $givenName;

    #[Property]
    public string $familyName;

    #[Property]
    #[DateTime]
    public ?Carbon $createdAt;

    #[Property]
    #[DateTime]
    public ?Carbon $updatedAt;

    public function baseUrl(): string
    {
        return 'https://localhost';
    }
}
