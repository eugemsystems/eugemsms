<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Concerns;

use Modules\Intelligence\Domain\DataObjects\ReportResult;

/**
 * Livewire cannot hydrate an arbitrary readonly DataObject into a
 * public property, so a `ReportResult` is held as a plain array, with a
 * hard cap on the rows sent back to the browser.
 */
trait PresentsReportResults
{
    /** Rows shown on screen — the full set is for export/scheduled delivery, not the page. */
    public const int DISPLAY_ROW_CAP = 200;

    /**
     * @return array{rows: array<int, array<string, mixed>>, rowCount: int, durationMs: int, wasRedirected: bool, redirectReason: ?string, truncated: bool}
     */
    protected function presentResult(ReportResult $result): array
    {
        return [
            'rows' => array_slice($result->rows, 0, self::DISPLAY_ROW_CAP),
            'rowCount' => $result->rowCount,
            'durationMs' => $result->durationMs,
            'wasRedirected' => $result->wasRedirected,
            'redirectReason' => $result->redirectReason,
            'truncated' => count($result->rows) > self::DISPLAY_ROW_CAP,
        ];
    }
}
