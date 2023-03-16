<?php

declare(strict_types=1);

namespace Intermax\EloquentJsonApiClient;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class QueryBuilder
{
    /**
     * @var array<string, mixed>
     */
    protected array $filters = [];

    protected array $sorts = [];

    protected string $includes = '';

    protected array $operators = [
        '=' => 'eq'
    ];

    public function __construct(protected Model $model)
    {

    }

    public function where(string $property, mixed $operator, mixed $value = null): static
    {
        if (is_null($value)) {
            $value = $operator;
            $operator = '=';
        }

        $this->filters[$property][$this->operators[$operator]] = $value;

        return $this;
    }

    /**
     * @param string $property
     * @param array<int, mixed> $values
     * @return $this
     */
    public function whereIn(string $property, array $values): static
    {
        $this->filters[$property][$this->operators['=']] = implode(',', $values);

        return $this;
    }

    public function find(mixed $id): Model
    {
        $response = Http::get($this->model->baseUrl().$this->toQuery($id));

        return $this->model->hydrate($response->json('data'), $response->json('included'));
    }

    public function with(...$relations): static
    {
        $first = Arr::first($relations);
        if (is_array($first)) {
            $relations = $first;
        }

        $this->includes = Str::of($this->includes)
            ->append(','.implode(',', $relations))
            ->ltrim(',')
            ->toString();

        return $this;
    }

    public function toQuery($id = null): string
    {
        $uri = $this->model->uri();

        if (! is_null($id)) {
            $uri .= '/'.$id;
        }

        return Str::of($uri.'?'.http_build_query(array_filter([
            'filter' => $this->filters,
            'include' => $this->includes,
        ])))->rtrim('?')->toString();
    }
}
