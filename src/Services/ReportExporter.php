<?php

namespace Meita\ReportsGenerator\Services;

use Meita\ReportsGenerator\Services\Exports\DataArrayExport;

class ReportExporter
{
    public function __construct(private ReportResult $result)
    {
    }

    public function toJsonApi(string $type = 'report-row'): array
    {
        $resources = [];
        foreach ($this->result->rows as $index => $row) {
            $attributes = (array) $row;
            $id = $attributes['id'] ?? (string) ($index + 1);

            $resources[] = [
                'type' => $type,
                'id' => (string) $id,
                'attributes' => $attributes,
            ];
        }

        return [
            'data' => $resources,
            'meta' => $this->result->meta,
        ];
    }

    public function toXml(string $root = 'report', string $rowNode = 'row'): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $rootElement = $doc->createElement($root);

        foreach ($this->result->rows as $row) {
            $rowElement = $doc->createElement($rowNode);
            foreach ((array) $row as $key => $value) {
                $cell = $doc->createElement($key);
                $cell->appendChild($doc->createTextNode((string) $value));
                $rowElement->appendChild($cell);
            }
            $rootElement->appendChild($rowElement);
        }

        if (!empty($this->result->meta)) {
            $metaElement = $doc->createElement('meta');
            foreach ($this->result->meta as $key => $value) {
                $metaChild = $doc->createElement($key, is_scalar($value) ? (string) $value : json_encode($value));
                $metaElement->appendChild($metaChild);
            }
            $rootElement->appendChild($metaElement);
        }

        $doc->appendChild($rootElement);
        return $doc->saveXML() ?: '';
    }

    public function toCsv(string $delimiter = ',', string $enclosure = '"'): string
    {
        $rows = $this->result->rows;
        if (empty($rows)) {
            return '';
        }

        $headers = array_keys((array) $rows[0]);
        $lines = [];
        $lines[] = $this->csvLine($headers, $delimiter, $enclosure);

        foreach ($rows as $row) {
            $lines[] = $this->csvLine(array_values((array) $row), $delimiter, $enclosure);
        }

        return implode("\n", $lines);
    }

    public function toHtmlTable(string $title = 'Report'): string
    {
        $rows = $this->result->rows;
        if (empty($rows)) {
            return '<table><thead></thead><tbody></tbody></table>';
        }

        $headers = array_keys((array) $rows[0]);

        $head = '<thead><tr>' . implode('', array_map(fn ($h) => '<th>' . e($h) . '</th>', $headers)) . '</tr></thead>';
        $bodyRows = [];
        foreach ($rows as $row) {
            $cells = array_map(fn ($cell) => '<td>' . e((string) $cell) . '</td>', (array) $row);
            $bodyRows[] = '<tr>' . implode('', $cells) . '</tr>';
        }
        $body = '<tbody>' . implode('', $bodyRows) . '</tbody>';

        return "<table>{$head}{$body}</table>";
    }

    public function toDataTables(): array
    {
        return [
            'data' => array_map(fn ($row) => (array) $row, $this->result->rows),
            'recordsTotal' => count($this->result->rows),
            'recordsFiltered' => count($this->result->rows),
            'meta' => $this->result->meta,
        ];
    }

    public function toPdf(string $title = 'Report', ?string $path = null, array $options = []): string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new \RuntimeException('dompdf/dompdf is not installed. Run composer require dompdf/dompdf');
        }

        $dompdf = new \Dompdf\Dompdf();
        $css = $options['css'] ?? '
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ccc; padding: 6px; font-size: 12px; }
            thead th { background: #f5f5f5; font-weight: bold; }
        ';
        $customTable = $options['html_table'] ?? $this->toHtmlTable($title);
        $html = '<html><head><style>' . $css . '</style></head><body><h2>' . e($title) . '</h2>' . $customTable . '</body></html>';
        $dompdf->loadHtml($html);
        $paper = $options['paper'] ?? 'a4';
        $orientation = $options['orientation'] ?? 'portrait';
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();
        $output = $dompdf->output();

        if ($path) {
            file_put_contents($path, $output);
            return $path;
        }

        return $output;
    }

    public function toWord(string $title = 'Report', ?string $path = null, array $options = []): string
    {
        if (!class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
            throw new \RuntimeException('phpoffice/phpword is not installed. Run composer require phpoffice/phpword');
        }

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $section->addTitle($title, 1);

        $rows = $this->result->rows;
        if (!empty($rows)) {
            $headers = array_keys((array) $rows[0]);
            $tableStyle = $options['table_style'] ?? ['borderSize' => 6, 'borderColor' => '999999'];
            $headerCellStyle = $options['header_cell_style'] ?? ['bgColor' => 'DDDDDD'];
            $headerFontStyle = $options['header_font_style'] ?? ['bold' => true];
            $rowCellStyle = $options['row_cell_style'] ?? [];

            $table = $section->addTable($tableStyle);
            $table->addRow();
            foreach ($headers as $header) {
                $table->addCell(null, $headerCellStyle)->addText($header, $headerFontStyle);
            }
            foreach ($rows as $row) {
                $table->addRow();
                foreach ((array) $row as $cell) {
                    $table->addCell(null, $rowCellStyle)->addText((string) $cell);
                }
            }
        }

        $path = $path ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('report_', true) . '.docx';
        $phpWord->save($path, 'Word2007');

        return $path;
    }

    public function toExcel(string $filename = 'report.xlsx', array $options = [])
    {
        if (!class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            throw new \RuntimeException('maatwebsite/excel is not installed. Run composer require maatwebsite/excel');
        }

        $export = new DataArrayExport($this->result->rows, $options);
        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
    }

    protected function csvLine(array $values, string $delimiter, string $enclosure): string
    {
        $escaped = array_map(function ($value) use ($delimiter, $enclosure) {
            $string = (string) $value;
            if (str_contains($string, $delimiter) || str_contains($string, "\n") || str_contains($string, $enclosure)) {
                $string = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $string) . $enclosure;
            }
            return $string;
        }, $values);

        return implode($delimiter, $escaped);
    }
}
