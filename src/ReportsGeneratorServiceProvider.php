<?php

namespace Meita\ReportsGenerator;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Meita\ReportsGenerator\Commands\RunReportCommand;

class ReportsGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/reports-generator.php', 'reports-generator');

        $this->app->singleton(ReportsGeneratorManager::class, function ($app) {
            return new ReportsGeneratorManager($app);
        });

        $this->app->alias(ReportsGeneratorManager::class, 'reports-generator');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/reports-generator.php' => config_path('reports-generator.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunReportCommand::class,
            ]);
        }

        Blade::directive('report', function ($expression) {
            return "<?php echo app('reports-generator')->render($expression); ?>";
        });
    }
}
