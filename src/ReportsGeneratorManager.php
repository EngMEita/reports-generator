<?php

namespace Meita\ReportsGenerator;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Meita\ReportsGenerator\Models\Report;
use Meita\ReportsGenerator\Services\ReportBuilder;
use Meita\ReportsGenerator\Services\ReportResult;

class ReportsGeneratorManager
{
    public function __construct(protected Application $app)
    {
    }

    /**
        * Create a builder from a Report model or slug.
        */
    public function report(string|Report $report): ReportBuilder
    {
        $model = $report instanceof Report
            ? $report
            : Report::query()->active()->where('slug', $report)->firstOrFail();

        return new ReportBuilder(
            $model,
            $this->connectionFor($model),
            $this->cacheStore()
        );
    }

    /**
     * Run a raw query (no persisted report).
     */
    public function raw(string $query, array $params = [], array $options = []): ReportResult
    {
        $builder = ReportBuilder::fromRaw(
            query: $query,
            params: $params,
            options: $options,
            connection: $options['connection'] ?? config('reports-generator.connection'),
            cacheStore: $this->cacheStore()
        );

        return $builder->run();
    }

    /**
     * Render the result into JSON (convenience for Blade @report directive).
     */
    public function render(string|Report $report, array $params = [], array $options = []): string
    {
        return $this->report($report)
            ->params($params)
            ->options($options)
            ->run()
            ->toJson();
    }

    protected function connectionFor(Report $report): string|null
    {
        return $report->connection ?: config('reports-generator.connection');
    }

    protected function cacheStore(): CacheFactory|\Illuminate\Cache\CacheManager|null
    {
        $store = config('reports-generator.cache_store');
        return $store ? Cache::store($store) : Cache::getFacadeRoot();
    }
}
