<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel as ExcelWriterType;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Domain\DataObjects\ReportExportFile;
use Modules\Intelligence\Domain\DataObjects\ReportResult;
use Modules\Intelligence\Domain\Support\Exports\ReportResultExport;

/**
 * ACT-GenerateReportExport (Book J INT-01 §5, `GET /api/v1/reports/{ulid}/export
 * ?format=pdf|excel|csv`, and the equivalent download from `Reports\Index`).
 * Renders an already-executed `ReportResult` — the result itself is never
 * re-run or re-authorised here, that happened before this is called.
 */
final class GenerateReportExportAction extends Action
{
    protected bool $transactional = false;

    public function execute(ReportResult $result, string $format, string $reportName): ReportExportFile
    {
        $slug = Str::slug($reportName) !== '' ? Str::slug($reportName) : 'report';

        return match ($format) {
            'csv' => new ReportExportFile(
                content: Excel::raw(new ReportResultExport($result->rows), ExcelWriterType::CSV),
                mimeType: 'text/csv',
                filename: "{$slug}.csv",
            ),
            'excel' => new ReportExportFile(
                content: Excel::raw(new ReportResultExport($result->rows), ExcelWriterType::XLSX),
                mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                filename: "{$slug}.xlsx",
            ),
            'pdf' => new ReportExportFile(
                content: Pdf::loadHTML($this->toHtml($reportName, $result))->output(),
                mimeType: 'application/pdf',
                filename: "{$slug}.pdf",
            ),
            default => throw new InvalidArgumentException("[{$format}] is not a supported export format."),
        };
    }

    private function toHtml(string $reportName, ReportResult $result): string
    {
        $columns = $result->rows === [] ? [] : array_keys($result->rows[0]);

        $head = implode('', array_map(fn (string $c): string => '<th>'.e($c).'</th>', $columns));
        $body = implode('', array_map(
            fn (array $row): string => '<tr>'.implode('', array_map(fn ($v): string => '<td>'.e((string) $v).'</td>', array_values($row))).'</tr>',
            $result->rows,
        ));

        return '<h3>'.e($reportName).'</h3><table border="1" cellspacing="0" cellpadding="4"><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table>';
    }
}
