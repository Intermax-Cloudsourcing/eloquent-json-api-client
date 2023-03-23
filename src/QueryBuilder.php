<?php

declare(strict_types=1);

namespace Intermax\EloquentJsonApiClient;

use Closure;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class QueryBuilder extends Builder
{
    /**
     * @var array<string, mixed>
     */
    protected array $filters = [];

    protected string $sorts = '';

    protected string $includes = '';

    /**
     * @var array<string, mixed>
     */
    protected array $page = [];

    /**
     * @var array<string, string>
     */
    protected array $apiOperators = [
        '=' => 'eq',
        '<>' => 'nq',
        '!=' => 'nq',
        '>' => 'gt',
        '>=' => 'gte',
        '<' => 'lt',
        '<=' => 'lte',
        'like' => 'contains',
    ];

    public function __construct(protected $model)
    {
        parent::__construct(new \Illuminate\Database\Query\Builder(new class implements ConnectionInterface
        {
            public function table($table, $as = null)
            {
                // TODO: Implement table() method.
            }

            public function raw($value)
            {
                // TODO: Implement raw() method.
            }

            public function selectOne($query, $bindings = [], $useReadPdo = true)
            {
                // TODO: Implement selectOne() method.
            }

            public function select($query, $bindings = [], $useReadPdo = true)
            {
                // TODO: Implement select() method.
            }

            public function cursor($query, $bindings = [], $useReadPdo = true)
            {
                // TODO: Implement cursor() method.
            }

            public function insert($query, $bindings = [])
            {
                // TODO: Implement insert() method.
            }

            public function update($query, $bindings = [])
            {
                // TODO: Implement update() method.
            }

            public function delete($query, $bindings = [])
            {
                // TODO: Implement delete() method.
            }

            public function statement($query, $bindings = [])
            {
                // TODO: Implement statement() method.
            }

            public function affectingStatement($query, $bindings = [])
            {
                // TODO: Implement affectingStatement() method.
            }

            public function unprepared($query)
            {
                // TODO: Implement unprepared() method.
            }

            public function prepareBindings(array $bindings)
            {
                // TODO: Implement prepareBindings() method.
            }

            public function transaction(Closure $callback, $attempts = 1)
            {
                // TODO: Implement transaction() method.
            }

            public function beginTransaction()
            {
                // TODO: Implement beginTransaction() method.
            }

            public function commit()
            {
                // TODO: Implement commit() method.
            }

            public function rollBack()
            {
                // TODO: Implement rollBack() method.
            }

            public function transactionLevel()
            {
                // TODO: Implement transactionLevel() method.
            }

            public function pretend(Closure $callback)
            {
                // TODO: Implement pretend() method.
            }

            public function getDatabaseName()
            {
                // TODO: Implement getDatabaseName() method.
            }
        }));
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and'): static
    {
        if (is_null($value)) {
            $value = $operator;
            $operator = '=';
        }

        if (strtolower($operator) === 'like') {
            $value = str_replace('%', '', $value);
        }

        $this->filters[$column][$this->apiOperators[$operator]] = $value;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false): static
    {
        $this->filters[$column][$this->apiOperators['=']] = implode(',', $values);

        return $this;
    }

    /**
     * @throws RequestException
     */
    public function find($id, $columns = ['*']): Model|null
    {
        $response = Http::withHeaders($this->model->headers())->get($this->model->baseUrl().$this->toQuery($id))->throwUnlessStatus(404);

        if ($response->failed()) {
            return null;
        }

        return $this->model->hydrate($response->json('data'), $response->json('included'));
    }

    /**
     * @return $this
     *
     * @throws NotImplementedException
     */
    public function with($relations, $callback = null): static
    {
        if ($callback instanceof Closure) {
            throw new NotImplementedException('with() callback not implemented.');
        }

        $relations = is_string($relations) ? func_get_args() : $relations;

        $this->includes = Str::of($this->includes)
            ->append(','.implode(',', $relations))
            ->ltrim(',')
            ->toString();

        return $this;
    }

    public function orderBy($column, $direction = 'asc'): static
    {
        if (! in_array($direction, ['asc', 'desc'])) {
            throw new InvalidArgumentException('Sort direction can only be asc or desc.');
        }

        $prefixes = [
            'asc' => '',
            'desc' => '-',
        ];

        $this->sorts = Str::of($this->sorts)
            ->append(','.$prefixes[$direction].$column)
            ->ltrim(',')
            ->toString();

        return $this;
    }

    /**
     * @return Collection<int, Model>
     */
    public function get($columns = ['*']): Collection
    {
        $response = $this->performCollectionQuery();

        return $this->hydrateCollection($response);
    }

    /**
     * @throws Exception
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null): LengthAwarePaginatorContract
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

    public function toQuery(string|int|null $id = null): string
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
        return Http::withHeaders($this->model->headers())->get($this->model->baseUrl().$this->toQuery());
    }

    /**
     * @return Collection<int, Model>
     */
    public function hydrateCollection(Response $response): Collection
    {
        return (new Collection(
            $response->json('data')
        ))->map(
            fn ($item) => $this->model->hydrate($item, $response->json('included'))
        );
    }
}
