<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\ReleaseFinalPayData;
use Modules\People\Domain\Exceptions\ExitClearanceIncompleteException;
use Modules\People\Models\StaffExitChecklist;

/**
 * ACT-ReleaseFinalPay (Book C PPL-04 §4/BR-PPL-04-021, AC-PPL-04-008).
 * Only gates the release itself — the actual terminal-pay
 * computation (leave encashment, notice pay, loan recovery per
 * BR-PPL-05-024) is `PPL-05`'s own deferred scope; this Action just
 * records that clearance is complete and release has happened.
 */
final class ReleaseFinalPayAction extends Action
{
    public function execute(ReleaseFinalPayData $data): StaffExitChecklist
    {
        $checklist = StaffExitChecklist::findOrFail($data->checklistId);

        if (! $checklist->isFullyCleared()) {
            throw ExitClearanceIncompleteException::forItems($checklist->id, $checklist->outstandingItemLabels());
        }

        return $this->transaction(function () use ($checklist, $data): StaffExitChecklist {
            $checklist->update([
                'final_pay_released_at' => Carbon::now(),
                'final_pay_released_by' => $data->releasedByUserId,
            ]);

            return $checklist;
        });
    }
}
