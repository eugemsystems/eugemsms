<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Models\SickBayAdmission;

/**
 * ACT-DischargeSickBay (Book G BRD-06 §2 — `SickBayDischarge` intent).
 * Once discharged, the admission no longer matches
 * `SickBayAdmission::CURRENTLY_ADMITTED_STATUSES`, so the next roll
 * call pre-populates the learner normally again.
 */
final class DischargeSickBayAction extends Action
{
    public function execute(int $admissionId, int $dischargedByUserId, string $destination, ?string $notes = null): SickBayAdmission
    {
        $admission = SickBayAdmission::findOrFail($admissionId);

        return $this->transaction(fn (): SickBayAdmission => tap($admission)->update([
            'discharged_at' => Carbon::now(),
            'discharged_by' => $dischargedByUserId,
            'discharge_destination' => $destination,
            'discharge_notes' => $notes,
            'status' => 'discharged',
        ]));
    }
}
