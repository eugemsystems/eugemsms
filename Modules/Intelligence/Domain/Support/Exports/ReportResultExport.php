<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Support\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Book J INT-01 §5 (`GET /api/v1/reports/{ulid}/export`, `format=excel|csv`).
 * A single generic exporter for any already-executed `ReportResult` — the
 * report's own selected fields decide the columns, this only shapes them for
 * `maatwebsite/excel`'s writer.
 */
final readonly class ReportResultExport implements FromArray, WithHeadings
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private array $rows,
    ) {}

    public function array(): array
    {
        return array_map(fn (array $row): array => array_values($row), $this->rows);
    }

    public function headings(): array
    {
        return $this->rows === [] ? [] : array_keys($this->rows[0]);
    }
}
