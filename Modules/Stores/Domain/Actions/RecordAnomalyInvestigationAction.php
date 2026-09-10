<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\ConsumptionAnomaly;

/**
 * ACT-RecordAnomalyInvestigation (Book H1 FIN-09 §7/BR-FIN-09-023). An
 * anomaly is never auto-dismissed — a note is always required, whether
 * or not the variance turned out to have an explanation. An unexplained
 * anomaly on a high-risk item escalates to the bursar (`status =
 * 'escalated'`) instead of quietly resolving.
 */
final class RecordAnomalyInvestigationAction extends Action
{
    public function execute(int $anomalyId, string $investigationNote, int $reviewedByUserId, bool $explained): ConsumptionAnomaly
    {
        $anomaly = ConsumptionAnomaly::with('item')->findOrFail($anomalyId);

        if (trim($investigationNote) === '') {
            throw ValidationException::withMessages([
                'investigationNote' => 'An investigation note is required — an anomaly is never auto-dismissed (BR-FIN-09-023).',
            ]);
        }

        $status = ! $explained && $anomaly->item->is_high_risk ? 'escalated' : 'resolved';

        return $this->transaction(fn (): ConsumptionAnomaly => tap($anomaly)->update([
            'investigation_note' => $investigationNote,
            'reviewed_by' => $reviewedByUserId,
            'status' => $status,
        ]));
    }
}
