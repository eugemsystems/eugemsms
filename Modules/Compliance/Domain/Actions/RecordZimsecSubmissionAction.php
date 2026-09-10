<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\DataObjects\RecordZimsecSubmissionData;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-RecordZimsecSubmission (Book H3 CMP-01 §1). A human submits
 * through ZIMSEC's own Online Candidate Registration System — this
 * only records that it happened, once the school has exported.
 */
final class RecordZimsecSubmissionAction extends Action
{
    public function execute(RecordZimsecSubmissionData $data): ZimsecRegistration
    {
        return $this->transaction(function () use ($data): ZimsecRegistration {
            $registration = ZimsecRegistration::findOrFail($data->registrationId);

            if ($registration->status !== 'exported') {
                throw new InvalidStateTransitionException(
                    "ZIMSEC registration #{$registration->id} must be exported before it can be recorded as submitted (currently '{$registration->status}').",
                    ['registration_id' => $registration->id, 'status' => $registration->status],
                );
            }

            $registration->update([
                'status' => 'submitted',
                'submitted_at' => Carbon::now(),
                'submitted_by' => $data->submittedByUserId,
                'zimsec_reference' => $data->zimsecReference,
            ]);

            return $registration;
        });
    }
}
