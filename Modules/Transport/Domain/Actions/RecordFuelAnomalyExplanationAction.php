<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Transport\Models\FuelLog;

/**
 * ACT-RecordFuelAnomalyExplanation (Book H2 OPS-01 §3/§4/BR-OPS-01-014).
 * An anomaly is never dismissed without a recorded explanation — the
 * same "never auto-dismissed" convention
 * `Modules\Stores\Domain\Actions\RecordAnomalyInvestigationAction`
 * (Book H1 FIN-09) already uses.
 */
final class RecordFuelAnomalyExplanationAction extends Action
{
    public function execute(int $fuelLogId, string $explanation, int $reviewedByUserId): FuelLog
    {
        $fuelLog = FuelLog::findOrFail($fuelLogId);

        if (trim($explanation) === '') {
            throw ValidationException::withMessages([
                'explanation' => 'A fuel anomaly is never dismissed without a recorded explanation (BR-OPS-01-014).',
            ]);
        }

        return $this->transaction(fn (): FuelLog => tap($fuelLog)->update([
            'anomaly_explanation' => $explanation,
            'anomaly_reviewed_by' => $reviewedByUserId,
        ]));
    }
}
