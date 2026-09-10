<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\DataObjects\RecordZimsecRemittanceData;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-RecordZimsecRemittance (Book H3 CMP-01 §3/BR-CMP-01-007). Money
 * actually sent to ZIMSEC can never exceed money actually collected —
 * enforced here rather than left to `CloseZimsecRegistrationAction`
 * to notice after the fact.
 */
final class RecordZimsecRemittanceAction extends Action
{
    public function execute(RecordZimsecRemittanceData $data): ZimsecRegistration
    {
        return $this->transaction(function () use ($data): ZimsecRegistration {
            $registration = ZimsecRegistration::findOrFail($data->registrationId);
            $newRemitted = $registration->remitted_minor + $data->amountMinor;

            if ($newRemitted > $registration->collected_minor) {
                throw new InvalidStateTransitionException(
                    "Cannot remit {$newRemitted} minor units for ZIMSEC registration #{$registration->id}: only {$registration->collected_minor} has been collected.",
                    ['registration_id' => $registration->id, 'attempted_remitted_minor' => $newRemitted, 'collected_minor' => $registration->collected_minor],
                );
            }

            $registration->update(['remitted_minor' => $newRemitted]);

            return $registration;
        });
    }
}
