<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\DecideScholarshipApplicationData;
use Modules\Finance\Domain\Events\ApplicationDecided;
use Modules\Finance\Models\ScholarshipApplication;

/**
 * ACT-DecideScholarshipApplication (Book K FIN-07 §2/§4/BR-FIN-07-006).
 * Records the committee's decision and its rationale permanently — the
 * decision itself does not grant an award; `GrantAwardAction` (a
 * separate, explicit step per §5's own screen split) does that,
 * reading `application_id` back.
 */
final class DecideScholarshipApplicationAction extends Action
{
    public function execute(DecideScholarshipApplicationData $data): ScholarshipApplication
    {
        $application = ScholarshipApplication::query()->findOrFail($data->applicationId);

        return $this->transaction(function () use ($application, $data): ScholarshipApplication {
            $application->update([
                'status' => $data->status,
                'committee_notes' => $data->committeeNotes,
                'rejection_reason' => $data->rejectionReason,
                'decided_by' => $data->decidedByUserId,
                'decided_at' => Carbon::now(),
            ]);

            event(new ApplicationDecided($application->fresh()));

            return $application->fresh();
        });
    }
}
