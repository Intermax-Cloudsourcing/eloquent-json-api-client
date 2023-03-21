<?php

declare(strict_types=1);

namespace Intermax\EloquentJsonApiClient;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Intermax\EloquentJsonApiClient\Attributes\DateTime;
use Intermax\EloquentJsonApiClient\Attributes\Property;
use Intermax\EloquentJsonApiClient\Attributes\ReadOnlyProperty;
use Intermax\EloquentJsonApiClient\Attributes\Relationship;
use Intermax\EloquentJsonApiClient\Attributes\WriteOnlyProperty;
use ReflectionClass;
use ReflectionProperty;

abstract class Model
{
    protected int $perPage = 30;

    public bool $exists = false;

    /**
     * @var array<string, string>
     */
    private array $requestWithoutBodyHeaders = [
        'Accept' => 'application/vnd.api+json',
    ];

    /**
     * @var array<string, string>
     */
    private array $requestWithBodyHeaders = [
        'Accept' => 'application/vnd.api+json',
        'Content-Type' => 'application/vnd.api+json',
    ];

    #[Property]
    public string $id;

    final public function __construct()
    {
    }

    public function uri(): string
    {
        return Str::of((new ReflectionClass($this))->getShortName())
            ->lower()
            ->plural()
            ->prepend('/')
            ->toString();
    }

    public function type(): string
    {
        return Str::of((new ReflectionClass($this))->getShortName())
            ->plural()
            ->camel()
            ->toString();
    }

    public function perPage(): int
    {
        return $this->perPage;
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
     * @param  array<string, mixed>  $item
     * @param  array<int, mixed>|null  $included
     */
    public function hydrate(array $item, array|null $included = null, bool $createNewInstance = true): static
    {
        if (is_null($included)) {
            $included = [];
        }

        $included = Arr::keyBy($included, fn ($includedItem) => $includedItem['id'].'-'.$includedItem['type']);

        if ($createNewInstance) {
            $model = new static();
        } else {
            $model = $this;
        }

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
        $pendingRequest = Http::withHeaders(array_merge($this->requestWithBodyHeaders, $this->headers()))->throw();

        $body = [
            'type' => $this->type(),
            'attributes' => $this->propertiesToArray(withReadOnly: false),
        ];

        if ($this->exists && isset($this->id)) {
            $response = $pendingRequest->patch($this->baseUrl().$this->uri().'/'.$this->id, [
                'id' => $this->id,
                ...$body,
            ]);
        } else {
            $response = $pendingRequest->post($this->baseUrl().$this->uri(), $body);
        }

        $this->hydrate(
            item: $response->json('data'),
            createNewInstance: false
        );

        $this->exists = true;

        return true;
    }

    public function delete(): bool
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function propertiesToArray(bool $withId = false, bool $withReadOnly = true, bool $withWriteOnly = true): array
    {
        $all = $this->filterProperties(new ReflectionClass($this), Property::class);

        $filtered = array_filter($all, function (ReflectionProperty $property) use ($withId, $withReadOnly, $withWriteOnly) {
            if (
                (! $withId && $property->name == 'id')
                || (! $withReadOnly && count($property->getAttributes(ReadOnlyProperty::class)))
                || (! $withWriteOnly && count($property->getAttributes(WriteOnlyProperty::class)))
            ) {
                return false;
            }

            return true;
        });

        $properties = [];

        /** @var ReflectionProperty $reflectionProperty */
        foreach ($filtered as $reflectionProperty) {
            $name = $reflectionProperty->name;

            $properties[$name] = $this->$name;
        }

        return $properties;
    }

//    protected function pendingRequestWithContent(): PendingRequest
//    {
//        return Http
//    }
//
//    protected function pendingRequestWithoutContent(): PendingRequest
//    {
//        return Http::
//    }

    /**
     * @param  ReflectionClass<Model>  $reflectionClass
     * @param  class-string  ...$attributes
     * @return array<int, ReflectionProperty>
     */
    private function filterProperties(ReflectionClass $reflectionClass, string ...$attributes): array
    {
        return array_values(array_filter(
            $reflectionClass->getProperties(),
            function (ReflectionProperty $value) use ($attributes) {
                foreach ($attributes as $attribute) {
                    $hasAttributes = (bool) count($value->getAttributes($attribute));

                    if (! $hasAttributes) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }
}
