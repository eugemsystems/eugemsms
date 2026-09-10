<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\Exceptions\ZimsecFeeShortfallException;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CloseZimsecRegistration (Book H3 CMP-01 §3 ⭐/BR-CMP-01-007
 * (AC-CMP-01-002)). Refuses to close while entry fees billed exceed
 * what's been collected — the shortfall is the recurring source of
 * unexplained deficit this rule exists to catch before it's forgotten.
 */
final class CloseZimsecRegistrationAction extends Action
{
    public function execute(int $registrationId): ZimsecRegistration
    {
        return $this->transaction(function () use ($registrationId): ZimsecRegistration {
            $registration = ZimsecRegistration::findOrFail($registrationId);

            if ($registration->total_fees_minor > $registration->collected_minor) {
                throw ZimsecFeeShortfallException::forRegistration(
                    $registration->id,
                    $registration->total_fees_minor,
                    $registration->collected_minor,
                );
            }

            $registration->update(['status' => 'closed']);

            return $registration;
        });
    }
}
