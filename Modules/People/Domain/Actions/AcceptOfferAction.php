<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\AcceptOfferData;
use Modules\People\Models\Application;

/**
 * ACT-AcceptOffer (Book C PPL-02 §4/BR-PPL-02-007). Acceptance itself
 * carries no money — `PayAcceptanceDepositAction` is the next, and
 * final, step before conversion, and must happen within
 * `intake.deposit_deadline_days` or the offer lapses
 * (`ExpireOfferAction`, not enforced automatically here since no job
 * scheduler exists yet).
 */
final class AcceptOfferAction extends Action
{
    public function execute(AcceptOfferData $data): Application
    {
        $application = Application::findOrFail($data->applicationId);

        if ($application->status !== 'offered') {
            throw new InvalidStateTransitionException(
                "An offer can only be accepted from [offered]; this application is [{$application->status}].",
                ['status' => $application->status],
            );
        }

        if ($application->offer_expires_at !== null && $application->offer_expires_at->isPast()) {
            throw new InvalidStateTransitionException(
                'This offer has expired and can no longer be accepted.',
                ['offer_expires_at' => $application->offer_expires_at->toIso8601String()],
            );
        }

        return $this->transaction(function () use ($application): Application {
            $application->update(['status' => 'accepted', 'accepted_at' => Carbon::now()]);

            return $application;
        });
    }
}
