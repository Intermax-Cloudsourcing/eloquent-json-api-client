<?php

declare(strict_types=1);

namespace Intermax\EloquentJsonApiClient;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Intermax\EloquentJsonApiClient\Attributes\DateTime;
use Intermax\EloquentJsonApiClient\Attributes\Property;
use Intermax\EloquentJsonApiClient\Attributes\Relationship;
use ReflectionClass;
use ReflectionProperty;

abstract class Model
{
    /**
     * @var array<int, string>
     */
    protected array $dates = [
        'createdAt',
        'updatedAt',
    ];

    public function uri(): string
    {
        return Str::of((new ReflectionClass($this))->getShortName())
            ->lower()
            ->plural()
            ->prepend('/')
            ->toString();
    }

    abstract public function baseUrl(): string;

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [];
    }

    public static function query(): QueryBuilder
    {
        return (new static())->newQuery();
    }

    public function newQuery(): QueryBuilder
    {
        return new QueryBuilder($this);
    }

    public static function find(mixed $id): ?static
    {
        return static::query()->find($id);
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, mixed>|null $included
     * @return static
     */
    public function hydrate(array $item, array|null $included = null): static
    {
        if (is_null($included)) {
            $included = [];
        }

        $included = Arr::keyBy($included, fn ($includedItem) => $includedItem['id'].'-'.$includedItem['type']);

        $model = new static();

        $reflectionClass = new ReflectionClass($model);

        $fillable = Arr::pluck($this->filterProperties($reflectionClass, Property::class), 'name');

        $dates = Arr::pluck($this->filterProperties($reflectionClass, Property::class, DateTime::class), 'name');

        $hydrator = function (Model $model, string $property, mixed $value) use ($dates, $fillable) {
            if (in_array($property, $fillable)) {
                if (in_array($property, $dates)) {
                    $value = Carbon::parse($value);
                }
                $model->$property = $value;
            }
        };

        $hydrator($model, 'id', $item['id']);

        foreach ($item['attributes'] as $attribute => $value) {
            $hydrator($model, $attribute, $value);
        }

        $relationProperties = Arr::keyBy($this->filterProperties($reflectionClass, Relationship::class), 'name');

        if (! array_key_exists('relationships', $item)) {
            return $model;
        }

        foreach ($item['relationships'] as $relationshipName => $relationship) {
            if (! array_key_exists($relationshipName, $relationProperties)) {
                continue;
            }

            /** @var Relationship $relationAttribute */
            $relationAttribute = $relationProperties[$relationshipName]
                ->getAttributes(Relationship::class)[0]->newInstance();

            $relationModel = $relationAttribute->createModel();

            if ($relationAttribute->type === RelationType::Many) {
                $hydratedRelation = new Collection();

                foreach ($relationship['data'] as $relationItem) {
                    $includedItem = $included[$relationItem['id'].'-'.$relationItem['type']] ?? null;

                    if (! is_null($includedItem)) {
                        $hydratedRelation->push(
                            $relationModel->hydrate($includedItem)
                        );
                    }
                }

                $model->{$relationshipName} = $hydratedRelation;
            } elseif ($relationAttribute->type === RelationType::One) {
                $relationItem = $relationship['data'];
                $includedItem = $included[$relationItem['id'].'-'.$relationItem['type']] ?? null;

                $model->{$relationshipName} = $relationModel->hydrate($includedItem);
            }
        }

        return $model;
    }

    public function save(): bool
    {
        return true;
    }

    /**
     * @param ReflectionClass $reflectionClass
     * @param class-string ...$attributes
     * @return array<int, ReflectionProperty>
     */
    private function filterProperties(ReflectionClass $reflectionClass, string ...$attributes): array
    {
        return array_values(array_filter(
            $reflectionClass->getProperties(),
            function (ReflectionProperty $value) use ($attributes) {
                $hasAttributes = true;

                foreach ($attributes as $attribute) {
                    $hasAttributes = (bool) count($value->getAttributes($attribute));
                }

                return $hasAttributes;
            }
        ));
    }
}
