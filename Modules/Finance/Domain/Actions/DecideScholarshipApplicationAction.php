<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
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

        if (! in_array($data->status, ['under_review', 'committee_review', 'approved', 'rejected', 'waitlisted'], true)) {
            throw new InvalidArgumentException("[{$data->status}] is not an application status a decision can set.");
        }

        if (in_array($application->status, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('A decided application is final — its rationale is kept permanently (BR-FIN-07-006).');
        }

        if ($application->status === 'draft') {
            throw new InvalidArgumentException('A draft has not been submitted yet.');
        }

        if ($data->status === 'rejected' && ($data->rejectionReason === null || trim($data->rejectionReason) === '')) {
            throw new InvalidArgumentException('A rejection needs a reason.');
        }

        if (in_array($data->status, ['approved', 'rejected'], true) && ($data->committeeNotes === null || trim($data->committeeNotes) === '')) {
            throw new InvalidArgumentException('A decision needs the committee’s rationale, retained permanently.');
        }

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
