<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\PayAcceptanceDepositData;
use Modules\People\Domain\Support\ApplicationPaymentPoster;
use Modules\People\Models\Application;

/**
 * ACT-PayAcceptanceDeposit (Book C PPL-02 §3/§4/BR-PPL-02-004
 * (AC-PPL-02-003)). Posts to Refundable Deposits — a liability, not
 * income — because it is exactly that until `ConvertApplicationToStudentAction`
 * turns it into the learner's own credit.
 */
final class PayAcceptanceDepositAction extends Action
{
    public function __construct(
        private readonly ApplicationPaymentPoster $poster,
    ) {}

    public function execute(PayAcceptanceDepositData $data): Application
    {
        $application = Application::findOrFail($data->applicationId);

        if ($application->status !== 'accepted') {
            throw new InvalidStateTransitionException(
                "An acceptance deposit can only be paid from [accepted]; this application is [{$application->status}].",
                ['status' => $application->status],
            );
        }

        $intake = $application->intake;

        return $this->transaction(function () use ($application, $intake, $data): Application {
            $receipt = $this->poster->post(
                application: $application,
                termId: $data->termId,
                receiptType: 'deposit',
                amountMinor: $intake->acceptance_deposit_minor,
                currency: $intake->acceptance_deposit_currency,
                tenderType: $data->tenderType,
                bankAccountId: $data->bankAccountId,
                targetAccountId: $data->refundableDepositsAccountId,
                receivedByUserId: $data->receivedByUserId,
            );

            $application->update([
                'deposit_receipt_id' => $receipt->id,
                'status' => 'deposit_paid',
            ]);

            $intake->increment('places_accepted');

            return $application;
        });
    }
}
