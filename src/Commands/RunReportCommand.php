<?php

namespace Meita\ReportsGenerator\Commands;

use Illuminate\Console\Command;
use Meita\ReportsGenerator\Facades\ReportsGenerator;
use Meita\ReportsGenerator\Models\Report;

class RunReportCommand extends Command
{
    protected $signature = 'reports:run {slug} {--params=} {--format=table}';

    protected $description = 'Run a stored report and print the output.';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $params = $this->parseParams($this->option('params'));
        $format = $this->option('format');

        $report = Report::where('slug', $slug)->active()->first();

        if (!$report) {
            $this->error("Report [{$slug}] not found or inactive.");
            return self::FAILURE;
        }

        $result = ReportsGenerator::report($report)->params($params)->run();

        if ($format === 'json') {
            $this->line(json_encode($result->toArray(), JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $rows = $result->rows;

        if (empty($rows)) {
            $this->info('No rows.');
            return self::SUCCESS;
        }

        $headers = array_keys((array) $rows[0]);
        $rowsArray = array_map(fn ($row) => (array) $row, $rows);
        $this->table($headers, $rowsArray);

        return self::SUCCESS;
    }

    protected function parseParams(?string $raw): array
    {
        if (!$raw) {
            return [];
        }

        $parts = explode(',', $raw);
        $params = [];
        foreach ($parts as $part) {
            if (!str_contains($part, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $part, 2);
            $params[trim($key)] = trim($value);
        }

        return $params;
    }
}
