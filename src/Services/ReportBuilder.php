<?php

namespace Meita\ReportsGenerator\Services;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Meita\ReportsGenerator\Models\Report;

class ReportBuilder
{
    protected array $params = [];
    protected array $options = [];

    public function __construct(
        protected Report $report,
        protected string|null $connection = null,
        protected CacheFactory|\Illuminate\Cache\CacheManager|null $cacheStore = null
    ) {
        $this->options = $report->options ?? [];
    }

    public static function fromRaw(
        string $query,
        array $params = [],
        array $options = [],
        string|null $connection = null,
        CacheFactory|\Illuminate\Cache\CacheManager|null $cacheStore = null
    ): self {
        $report = new Report([
            'name' => 'raw-report-' . Str::random(6),
            'slug' => 'raw-report-' . Str::random(8),
            'base_query' => $query,
            'filters' => [],
            'options' => $options,
            'cache_ttl' => $options['cache_ttl'] ?? 0,
            'is_active' => true,
        ]);

        $builder = new self($report, $connection, $cacheStore);
        $builder->params($params);
        $builder->options($options);

        return $builder;
    }

    public function params(array $params): self
    {
        $this->params = $params;
        return $this;
    }

    public function options(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function run(): ReportResult
    {
        $connection = $this->getConnection();
        $bindings = $this->mergeFiltersWithParams();
        $cacheTtl = $this->resolveCacheTtl();

        $cacheKey = $this->makeCacheKey($bindings);

        if ($cacheTtl > 0 && $this->cacheStore) {
            $data = $this->cacheStore->remember($cacheKey, $cacheTtl, function () use ($connection, $bindings) {
                return $this->executeQuery($connection, $bindings);
            });
        } else {
            $data = $this->executeQuery($connection, $bindings);
        }

        return new ReportResult(
            name: $this->report->name,
            slug: $this->report->slug,
            rows: $data['rows'],
            meta: $data['meta']
        );
    }

    protected function executeQuery(ConnectionInterface $connection, array $bindings): array
    {
        $query = trim($this->report->base_query);

        if ($this->wantsPagination()) {
            $paginator = $this->paginate($connection, $query, $bindings);
            return [
                'rows' => $paginator->items(),
                'meta' => [
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                    ],
                ],
            ];
        }

        $rows = $connection->select($query, $bindings);

        return [
            'rows' => $rows,
            'meta' => [],
        ];
    }

    protected function paginate(ConnectionInterface $connection, string $query, array $bindings): LengthAwarePaginator
    {
        $currentRequest = app()->bound('request') ? request() : null;
        $page = (int) ($this->options['page'] ?? ($currentRequest?->query('page', 1) ?? 1));
        $perPage = (int) ($this->options['per_page'] ?? 15);

        $countQuery = "select count(*) as aggregate from ({$query}) as report_count";
        $total = $connection->selectOne($countQuery, $bindings)->aggregate ?? 0;

        $offset = ($page - 1) * $perPage;
        $pagedQuery = $query . " limit {$perPage} offset {$offset}";
        $items = $connection->select($pagedQuery, $bindings);

        return new \Illuminate\Pagination\LengthAwarePaginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            options: ['path' => $currentRequest?->url() ?? '', 'pageName' => 'page']
        );
    }

    protected function mergeFiltersWithParams(): array
    {
        $filters = $this->report->filters ?? [];

        foreach ($filters as $key => $filter) {
            if (!array_key_exists($key, $this->params) && array_key_exists('default', $filter)) {
                $this->params[$key] = $filter['default'];
            }
        }

        return $this->params;
    }

    protected function makeCacheKey(array $bindings): string
    {
        $base = 'report:' . $this->report->slug;
        if (empty($bindings)) {
            return $base;
        }

        return $base . ':' . md5(json_encode($bindings));
    }

    protected function resolveCacheTtl(): int
    {
        return (int) ($this->options['cache_ttl'] ?? $this->report->cache_ttl ?? config('reports-generator.cache_ttl'));
    }

    protected function wantsPagination(): bool
    {
        return (bool) ($this->options['paginate'] ?? false);
    }

    protected function getConnection(): ConnectionInterface
    {
        $connectionName = $this->connection ?? config('reports-generator.connection');
        return $connectionName ? DB::connection($connectionName) : DB::connection();
    }
}
