<?php

namespace Meita\ReportsGenerator\Services;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Meita\ReportsGenerator\Services\ReportExporter;

class ReportResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $name,
        public string $slug,
        public array $rows,
        public array $meta = []
    ) {
    }

    public function exporter(): ReportExporter
    {
        return new ReportExporter($this);
    }

    public function toCsv(string $delimiter = ',', string $enclosure = '"'): string
    {
        return $this->exporter()->toCsv($delimiter, $enclosure);
    }

    public function toHtmlTable(string $title = 'Report'): string
    {
        return $this->exporter()->toHtmlTable($title);
    }

    public function toDataTables(): array
    {
        return $this->exporter()->toDataTables();
    }

    public function toJsonApi(string $type = 'report-row'): array
    {
        return $this->exporter()->toJsonApi($type);
    }

    public function toXml(string $root = 'report', string $rowNode = 'row'): string
    {
        return $this->exporter()->toXml($root, $rowNode);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'rows' => $this->rows,
            'meta' => $this->meta,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
