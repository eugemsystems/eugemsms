<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Farm\Domain\DataObjects\FailCropCycleData;
use Modules\Farm\Domain\Events\CropCycleFailed;
use Modules\Farm\Models\CropCycle;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;

/**
 * ACT-FailCropCycle (Book H2 OPS-03 §4/BR-OPS-03-007/AC-OPS-03-004).
 * A failed cycle's accumulated cost reclassifies to a dedicated crop
 * failure expense line — visible on its own, never absorbed into
 * ordinary farm running costs — via one aggregate reclassification
 * journal (Dr Crop Failure Expense / Cr the account the cost
 * originally posted to). A reason is always required; failure is
 * normal in agriculture and must be recorded, not hidden.
 */
final class FailCropCycleAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(int $cropCycleId, FailCropCycleData $data): CropCycle
    {
        $cycle = CropCycle::findOrFail($cropCycleId);

        if (trim($data->failureReason) === '') {
            throw ValidationException::withMessages([
                'failureReason' => 'A reason is required to fail a crop cycle — failure must be visible, not hidden in general overhead (BR-OPS-03-007).',
            ]);
        }

        return $this->transaction(function () use ($cycle, $data): CropCycle {
            if ($cycle->total_cost_minor > 0) {
                $currency = Currency::from($cycle->currency);

                $this->postJournal->execute(new PostJournalData(
                    schoolId: $cycle->school_id,
                    academicYearId: $data->academicYearId,
                    termId: $data->termId,
                    journalType: 'CROP_FAILURE_WRITEOFF',
                    narration: "Crop cycle failure — {$cycle->cycle_reference}: {$data->failureReason}",
                    lines: [
                        new JournalLineData(accountId: $data->cropFailureExpenseAccountId, direction: 'DR', amount: Money::of($cycle->total_cost_minor, $currency)),
                        new JournalLineData(accountId: $data->originalExpenseAccountId, direction: 'CR', amount: Money::of($cycle->total_cost_minor, $currency)),
                    ],
                    effectiveAt: $data->effectiveAt ?? Carbon::now(),
                    postedByUserId: $data->performedByUserId,
                    sourceType: 'crop_cycle',
                    sourceId: $cycle->id,
                ));
            }

            $cycle->update([
                'status' => 'failed',
                'failure_reason' => $data->failureReason,
            ]);

            event(new CropCycleFailed($cycle));

            return $cycle;
        });
    }
}
