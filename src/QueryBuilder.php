<?php

declare(strict_types=1);

namespace Intermax\EloquentJsonApiClient;

use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class QueryBuilder
{
    /**
     * @var array<string, mixed>
     */
    protected array $filters = [];

    protected string $sorts = '';

    protected string $includes = '';

    /**
     * @var array<string, string>
     */
    protected array $page = [];

    protected array $operators = [
        '=' => 'eq',
        '<>' => 'nq',
        '!=' => 'nq',
        '>' => 'gt',
        '>=' => 'gte',
        '<' => 'lt',
        '<=' => 'lte',
        'like' => 'contains',
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

        if (strtolower($operator) === 'like') {
            $value = str_replace('%', '', $value);
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

    public function orderBy(string $property, string $direction = 'asc'): static
    {
        if (! in_array($direction, ['asc', 'desc'])) {
            throw new InvalidArgumentException('Sort direction can only be asc or desc.');
        }

        $prefixes = [
            'asc' => '',
            'desc' => '-',
        ];

        $this->sorts = Str::of($this->sorts)
            ->append(','.$prefixes[$direction].$property)
            ->ltrim(',')
            ->toString();

        return $this;
    }

    /**
     * @return Collection<int, Model>
     */
    public function get(): Collection
    {
        $response = $this->performCollectionQuery();

        return $this->hydrateCollection($response);
    }

    public function paginate(int|null $perPage = null, string $pageName = 'page', int|null $page = null): LengthAwarePaginatorContract
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $this->page = [
            'number' => $page,
            'size' => $perPage ?? $this->model->perPage(),
        ];

        $response = $this->performCollectionQuery();

        if (! $response->json('meta.total')) {
            throw new Exception('Cannot use paginate() without a total.');
        }

        return new LengthAwarePaginator(
            items: $this->hydrateCollection($response),
            total: $response->json('meta.total'),
            perPage: $response->json('meta.pageSize'),
            currentPage: $response->json('meta.currentPage'),
        );
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
            'sort' => $this->sorts,
            'page' => $this->page,
        ])))->rtrim('?')->toString();
    }

    public function performCollectionQuery(): Response
    {
        return Http::get($this->model->baseUrl() . $this->toQuery());
    }

    /**
     * @param Response $response
     * @return Collection
     */
    public function hydrateCollection(Response $response): Collection
    {
        return (new Collection(
            $response->json('data')
        ))->map(
            fn($item) => $this->model->hydrate($item, $response->json('included'))
        );
    }
}
