<?php

declare(strict_types=1);

namespace Intermax\EloquentJsonApiClient\Attributes;

use Attribute;
use Intermax\EloquentJsonApiClient\Model;
use Intermax\EloquentJsonApiClient\RelationType;

#[Attribute]
readonly class Relationship
{
    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(public string $model, public RelationType $type)
    {
    }

    public function createModel(): Model
    {
        $model = $this->model;

        return new $model();
    }
}
