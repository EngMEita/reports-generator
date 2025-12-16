<?php

namespace Meita\ReportsGenerator\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Meita\ReportsGenerator\ReportsGeneratorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ReportsGeneratorServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUpDatabase(): void
    {
        Schema::dropAllTables();

        Schema::create(config('reports-generator.table', 'reports'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('connection')->nullable();
            $table->text('description')->nullable();
            $table->text('base_query');
            $table->json('filters')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('cache_ttl')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('department');
            $table->boolean('active')->default(true);
        });

        $this->seedEmployees();
    }

    protected function seedEmployees(): void
    {
        \DB::table('employees')->insert([
            ['name' => 'Alice', 'department' => 'HR', 'active' => true],
            ['name' => 'Bob', 'department' => 'IT', 'active' => true],
            ['name' => 'Charlie', 'department' => 'IT', 'active' => false],
            ['name' => 'Dana', 'department' => 'Finance', 'active' => true],
        ]);
    }
}
