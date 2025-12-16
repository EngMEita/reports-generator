<?php

namespace Meita\ReportsGenerator\Tests;

use Meita\ReportsGenerator\Facades\ReportsGenerator;
use Meita\ReportsGenerator\Models\Report;

class ReportBuilderTest extends TestCase
{
    public function test_runs_stored_report_with_params(): void
    {
        Report::create([
            'name' => 'IT employees',
            'slug' => 'it-employees',
            'base_query' => 'select id, name, department from employees where department = :department and active = :active',
            'filters' => [],
            'options' => [],
            'cache_ttl' => 0,
            'is_active' => true,
        ]);

        $result = ReportsGenerator::report('it-employees')
            ->params(['department' => 'IT', 'active' => 1])
            ->run();

        $names = collect($result->rows)->pluck('name')->sort()->values()->all();

        $this->assertSame(['Bob'], $names);
    }

    public function test_uses_default_filters_when_param_missing(): void
    {
        Report::create([
            'name' => 'Active employees all departments',
            'slug' => 'active-employees',
            'base_query' => 'select id, name, department, active from employees where active = :active',
            'filters' => [
                'active' => ['default' => 1],
            ],
            'options' => [],
            'cache_ttl' => 0,
            'is_active' => true,
        ]);

        $result = ReportsGenerator::report('active-employees')->run();

        $names = collect($result->rows)->pluck('name')->sort()->values()->all();

        $this->assertSame(['Alice', 'Bob', 'Dana'], $names);
    }

    public function test_paginates_when_option_enabled(): void
    {
        Report::create([
            'name' => 'All employees paginated',
            'slug' => 'employees-paginated',
            'base_query' => 'select id, name from employees order by id asc',
            'filters' => [],
            'options' => [
                'paginate' => true,
                'per_page' => 2,
            ],
            'cache_ttl' => 0,
            'is_active' => true,
        ]);

        $result = ReportsGenerator::report('employees-paginated')
            ->options(['page' => 2]) // expect second page
            ->run();

        $pageMeta = $result->meta['pagination'] ?? [];

        $this->assertCount(2, $result->rows);
        $this->assertSame(2, $pageMeta['current_page'] ?? null);
        $this->assertSame(2, $pageMeta['per_page'] ?? null);
        $this->assertSame(2, $pageMeta['last_page'] ?? null);
        $this->assertSame(4, $pageMeta['total'] ?? null);
    }

    public function test_runs_raw_query_without_stored_definition(): void
    {
        $result = ReportsGenerator::raw(
            query: 'select count(*) as total_active from employees where active = :active',
            params: ['active' => 1]
        );

        $this->assertSame(3, $result->rows[0]->total_active ?? null);
    }

    public function test_exports_to_csv(): void
    {
        Report::create([
            'name' => 'CSV employees',
            'slug' => 'csv-employees',
            'base_query' => 'select id, name, department from employees order by id asc',
            'filters' => [],
            'options' => [],
            'cache_ttl' => 0,
            'is_active' => true,
        ]);

        $result = ReportsGenerator::report('csv-employees')->run();
        $csv = $result->toCsv();

        $this->assertStringContainsString("id,name,department", $csv);
        $this->assertStringContainsString("1,Alice,HR", $csv);
        $this->assertStringContainsString("4,Dana,Finance", $csv);
    }

    public function test_exports_to_datatables_array(): void
    {
        Report::create([
            'name' => 'DT employees',
            'slug' => 'dt-employees',
            'base_query' => 'select id, name from employees order by id asc',
            'filters' => [],
            'options' => [],
            'cache_ttl' => 0,
            'is_active' => true,
        ]);

        $result = ReportsGenerator::report('dt-employees')->run();
        $dt = $result->toDataTables();

        $this->assertSame(4, $dt['recordsTotal']);
        $this->assertSame(4, $dt['recordsFiltered']);
        $this->assertCount(4, $dt['data']);
        $this->assertSame(['id', 'name'], array_keys($dt['data'][0]));
    }

    public function test_exports_to_json_api(): void
    {
        Report::create([
            'name' => 'JSON API employees',
            'slug' => 'jsonapi-employees',
            'base_query' => 'select id, name from employees order by id asc',
            'filters' => [],
            'options' => [],
            'cache_ttl' => 0,
            'is_active' => true,
        ]);

        $result = ReportsGenerator::report('jsonapi-employees')->run();
        $jsonApi = $result->toJsonApi('employee');

        $this->assertArrayHasKey('data', $jsonApi);
        $this->assertCount(4, $jsonApi['data']);
        $this->assertSame('employee', $jsonApi['data'][0]['type']);
        $this->assertSame('1', $jsonApi['data'][0]['id']);
        $this->assertSame('Alice', $jsonApi['data'][0]['attributes']['name']);
    }

    public function test_exports_to_xml(): void
    {
        Report::create([
            'name' => 'XML employees',
            'slug' => 'xml-employees',
            'base_query' => 'select id, name from employees order by id asc limit 2',
            'filters' => [],
            'options' => [],
            'cache_ttl' => 0,
            'is_active' => true,
        ]);

        $result = ReportsGenerator::report('xml-employees')->run();
        $xml = $result->toXml('employees', 'employee');

        $this->assertStringContainsString('<employees>', $xml);
        $this->assertStringContainsString('<employee>', $xml);
        $this->assertStringContainsString('<name>Alice</name>', $xml);
        $this->assertStringContainsString('<name>Bob</name>', $xml);
    }
}
