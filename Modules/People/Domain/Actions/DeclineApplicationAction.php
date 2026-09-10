<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\DeclineApplicationData;
use Modules\People\Domain\Events\ApplicationDeclined;
use Modules\People\Models\Application;

/**
 * ACT-DeclineApplication (Book C PPL-02 §4/BR-PPL-02-012). A declined
 * applicant's data is retained for the CMP-03 period then purged —
 * that retention job is a separate, not-yet-built concern; this
 * Action only records the decision and the reason.
 */
final class DeclineApplicationAction extends Action
{
    private const array NOT_DECLINABLE = ['enrolled', 'declined', 'withdrawn', 'expired'];

    public function execute(DeclineApplicationData $data): Application
    {
        $application = Application::findOrFail($data->applicationId);

        if (in_array($application->status, self::NOT_DECLINABLE, true)) {
            throw new InvalidStateTransitionException(
                "An application in [{$application->status}] can no longer be declined.",
                ['status' => $application->status],
            );
        }

        return $this->transaction(function () use ($application, $data): Application {
            $wasOffered = $application->status === 'offered';

            $application->update([
                'status' => 'declined',
                'declined_reason' => $data->reason,
            ]);

            if ($wasOffered) {
                $application->intake->decrement('places_offered');
            }

            event(new ApplicationDeclined($application));

            return $application;
        });
    }
}
