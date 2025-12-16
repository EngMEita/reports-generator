# meita/reports-generator

Dynamic report generator for Laravel that stores report definitions (SQL + filters + options), runs them on demand with bindings, and returns ready-to-use collections/JSON. Includes CLI runner, Blade directive, caching, pagination, raw query support, exports (CSV/Excel/PDF/Word/print), and DataTables-ready output.

- **Namespace**: `Meita\ReportsGenerator`
- **Author**: Eng. Mohamed A. Eita (`maa1987@hotmail.com`)

## Features
- Store report definitions in the database (`name`, `slug`, `base_query`, `filters`, `options`, `cache_ttl`, `connection`, `is_active`).
- Run stored reports with runtime parameters or run raw SQL without persistence.
- Optional caching (per report or per call).
- Optional pagination (wraps the query with count + limit/offset).
- CLI command `php artisan reports:run` for quick checks/exports.
- Blade directive `@report('slug', [...])` returns JSON for quick embedding.
- Export helpers: CSV, print/HTML table, DataTables-ready array, Excel/PDF/Word (bundled).
- Extra formats: JSON:API payload, XML payload; styling options for Excel/PDF/Word exports (headers, colors, merged cells).
- Configurable table name, connection, cache store, and TTL.

## Installation
1. Add the package (if local path, adjust as needed):
   ```bash
   composer require meita/reports-generator
   ```
2. Publish config and migration:
   ```bash
   php artisan vendor:publish --provider="Meita\ReportsGenerator\ReportsGeneratorServiceProvider" --tag=config
   php artisan vendor:publish --provider="Meita\ReportsGenerator\ReportsGeneratorServiceProvider" --tag=migrations
   ```
3. Run the migration:
   ```bash
   php artisan migrate
   ```

## Configuration (config/reports-generator.php)
- `connection`: default DB connection for reports (nullable = app default).
- `cache_store`: cache store name (nullable = default cache).
- `cache_ttl`: default TTL in seconds (can be overridden per report or per call).
- `table`: table name for stored reports.

## Database structure
The `reports` table stores definitions:
- `name` (string), `slug` (unique), `description` (text, optional)
- `connection` (string, optional) to point to another DB connection
- `base_query` (text) — use named bindings like `:status`, `:from_date`
- `filters` (json, optional) — e.g. `{"status":{"default":"active"}}`
- `options` (json, optional) — e.g. `{"paginate":true,"per_page":25,"cache_ttl":300}`
- `cache_ttl` (int), `is_active` (bool)

### Creating a report (seeder/tinker)
```php
use Meita\ReportsGenerator\Models\Report;

Report::create([
    'name' => 'Employees by branch',
    'slug' => 'employees-by-branch',
    'description' => 'Active employees grouped by branch.',
    'base_query' => <<<'SQL'
        select e.id, e.name, e.position, b.name as branch_name
        from employees e
        join branches b on b.id = e.branch_id
        where (:status is null or e.status = :status)
          and (:branch_id is null or e.branch_id = :branch_id)
    SQL,
    'filters' => [
        'status' => ['default' => 'active'],
        'branch_id' => ['default' => null],
    ],
    'options' => [
        'paginate' => true,
        'per_page' => 25,
        'cache_ttl' => 300,
    ],
    'cache_ttl' => 0,
    'is_active' => true,
]);
```

## Usage

### Run a stored report in code
```php
use Meita\ReportsGenerator\Facades\ReportsGenerator;

$result = ReportsGenerator::report('employees-by-branch')
    ->params([
        'status' => 'active',
        'branch_id' => 5,
    ])
    ->options([
        'paginate' => true,   // enable pagination
        'per_page' => 20,
        'cache_ttl' => 120,   // seconds
    ])
    ->run();

$rows = $result->rows;      // array of stdClass rows
$json = $result->toJson();  // JSON payload
```

### Run a raw query (no stored definition)
```php
$sales = ReportsGenerator::raw(
    query: 'select id, total, created_at from orders where status = :status',
    params: ['status' => 'paid'],
    options: ['cache_ttl' => 60]
);
```

### Blade directive (returns JSON string)
```blade
@php($reportJson = @report('employees-by-branch', ['branch_id' => 2]))
<pre>{{ $reportJson }}</pre>
```

### Blade rendering example
```blade
@php($report = ReportsGenerator::report('employees-by-branch')->params(['branch_id' => 2])->run())

<table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Branch</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report->rows as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ $row->name }}</td>
                <td>{{ $row->branch_name }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
```

### CLI runner
```bash
php artisan reports:run employees-by-branch --params="status:active,branch_id:2" --format=json
```
- `--format=table` (default) prints a table.
- `--format=json` prints JSON.

## Exports (CSV / HTML / DataTables / Excel / PDF / Word / Print)
- كل التنسيقات مدمجة مع الباكچ:
  - CSV: `$result->toCsv()` returns string.
  - HTML table / print: `$result->toHtmlTable()` returns a `<table>` string.
  - DataTables-ready array: `$result->toDataTables()` returns `data`, `recordsTotal`, `recordsFiltered`, `meta`.
  - JSON:API: `$result->toJsonApi('employee')` returns JSON:API-compliant array.
  - XML: `$result->toXml('report', 'row')` returns an XML string.
  - Excel: `$result->exporter()->toExcel('employees.xlsx', [...options])`
  - PDF: `$result->exporter()->toPdf('Title', $path = null, [...options])`
  - Word: `$result->exporter()->toWord('Title', $path = null, [...options])`

### Examples
```php
$result = ReportsGenerator::report('employees-by-branch')->params(['branch_id' => 2])->run();

// CSV
$csv = $result->toCsv();

// DataTables JSON
return response()->json($result->toDataTables());

// Print/HTML
echo $result->toHtmlTable('Employees');

// Excel download (needs maatwebsite/excel)
return $result->exporter()->toExcel('employees.xlsx', [
    'heading_style' => [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => 'solid', 'color' => ['rgb' => '007bff']],
    ],
    'merge_cells' => ['A1:C1'],
    'column_widths' => ['A' => 12, 'B' => 25, 'C' => 20],
]);

// PDF (needs dompdf/dompdf)
$pdfBinary = $result->exporter()->toPdf('Employees', null, [
    'orientation' => 'landscape',
    'paper' => 'a4',
    'css' => 'table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ddd;padding:6px;}thead th{background:#222;color:#fff;}',
]); // or pass a path instead of null to save

// Word DOCX (needs phpoffice/phpword)
$docPath = $result->exporter()->toWord('Employees', storage_path('app/employees.docx'), [
    'table_style' => ['borderSize' => 6, 'borderColor' => '999999'],
    'header_cell_style' => ['bgColor' => '222222'],
    'header_font_style' => ['bold' => true, 'color' => 'FFFFFF'],
]);

// JSON:API payload
return response()->json($result->toJsonApi('employee'));

// XML payload
return response($result->toXml('employees', 'employee'), 200, ['Content-Type' => 'application/xml']);
```

## Options and filters
- **params**: named bindings that match `:placeholder` values inside `base_query`.
- **filters (stored)**: if a param is not provided at runtime, the `default` value from `filters` is injected.
- **options**:
  - `paginate` (bool) — paginate the SQL result.
  - `per_page` (int) — items per page (default 15).
  - `page` (int) — current page (default from request or 1).
  - `cache_ttl` (int) — cache result in seconds (0 disables).
  - `connection` (string) — override DB connection for this run.

## Caching
- Report definition has `cache_ttl`; you can override per call via `options(['cache_ttl' => 300])`.
- Cache key is built from `slug` + params hash.
- Configure cache store via `cache_store` in the config.

## Pagination
- Enable by setting `options['paginate'] = true` on the report or per call.
- Uses a wrapping count query: `select count(*) from (<base_query>)`.

## Safety notes
- Use named bindings (`:status`) instead of interpolating values to avoid SQL injection.
- Ensure `base_query` is valid on the chosen connection.
- Disable or restrict who can edit report definitions in production.

## Contributing
- Fork or add as a local path repo, adjust code, and run `composer dump-autoload`.
- PRs and issues are welcome.

## License
MIT
