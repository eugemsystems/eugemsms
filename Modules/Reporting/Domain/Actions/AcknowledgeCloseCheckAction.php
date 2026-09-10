<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Reporting\Domain\DataObjects\AcknowledgeCloseCheckData;
use Modules\Reporting\Domain\Events\CloseCheckAcknowledged;
use Modules\Reporting\Domain\Exceptions\BlockingCheckCannotBeAcknowledgedException;
use Modules\Reporting\Models\CloseCheckAcknowledgement;
use Modules\Reporting\Models\PeriodCloseChecklist;

/**
 * ACT-AcknowledgeCloseCheck (Book H3 FIN-12 §4 ⭐/BR-FIN-12-010
 * (AC-FIN-12-004/005)). A warning-level failure may be acknowledged
 * with a written reason; a blocking one never can — this is checked
 * against the checklist's own persisted `results`, not re-derived.
 * Once every warning on a `failed`-only-by-warnings run is
 * acknowledged, the checklist's `overall_status` becomes
 * `passed_with_acknowledgements`.
 */
final class AcknowledgeCloseCheckAction extends Action
{
    public function execute(AcknowledgeCloseCheckData $data): CloseCheckAcknowledgement
    {
        if (mb_strlen($data->reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'A written reason is required to acknowledge a check.']);
        }

        $checklist = PeriodCloseChecklist::findOrFail($data->checklistId);
        $check = collect($checklist->results)->firstWhere('code', $data->checkKey);

        if ($check === null) {
            throw new InvalidStateTransitionException("Check [{$data->checkKey}] is not part of checklist #{$checklist->id}.", ['check_key' => $data->checkKey]);
        }

        if ($check['blocking']) {
            throw BlockingCheckCannotBeAcknowledgedException::forCheck($data->checkKey);
        }

        return $this->transaction(function () use ($checklist, $data): CloseCheckAcknowledgement {
            $acknowledgement = CloseCheckAcknowledgement::create([
                'school_id' => $checklist->school_id,
                'checklist_id' => $checklist->id,
                'check_key' => $data->checkKey,
                'reason' => $data->reason,
                'acknowledged_by' => $data->acknowledgedByUserId,
                'acknowledged_at' => Carbon::now(),
                'approved_by' => $data->approvedByUserId,
            ]);

            $this->refreshOverallStatus($checklist);

            event(new CloseCheckAcknowledged($acknowledgement));

            return $acknowledgement;
        });
    }

    private function refreshOverallStatus(PeriodCloseChecklist $checklist): void
    {
        if ($checklist->blocking_failures > 0) {
            return;
        }

        $failingWarningKeys = collect($checklist->results)
            ->filter(fn (array $item): bool => ! $item['passed'] && ! $item['blocking'])
            ->pluck('code');

        $acknowledgedKeys = CloseCheckAcknowledgement::where('checklist_id', $checklist->id)->pluck('check_key');

        if ($failingWarningKeys->isNotEmpty() && $failingWarningKeys->diff($acknowledgedKeys)->isEmpty()) {
            $checklist->update(['overall_status' => 'passed_with_acknowledgements']);
        }
    }
}
