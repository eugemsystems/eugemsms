<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\PayApplicationFeeData;
use Modules\People\Domain\Support\ApplicationPaymentPoster;
use Modules\People\Models\Application;

/**
 * ACT-PayApplicationFee (Book C PPL-02 §4/BR-PPL-02-004). Application
 * fees are non-refundable by default and post straight to income —
 * never conflated with the acceptance deposit's Refundable Deposits
 * liability treatment.
 */
final class PayApplicationFeeAction extends Action
{
    public function __construct(
        private readonly ApplicationPaymentPoster $poster,
    ) {}

    public function execute(PayApplicationFeeData $data): Application
    {
        $application = Application::findOrFail($data->applicationId);

        if ($application->status !== 'fee_pending') {
            throw new InvalidStateTransitionException(
                "An application fee can only be paid from [fee_pending]; this one is [{$application->status}].",
                ['status' => $application->status],
            );
        }

        $intake = $application->intake;

        return $this->transaction(function () use ($application, $intake, $data): Application {
            $receipt = $this->poster->post(
                application: $application,
                termId: $data->termId,
                receiptType: 'sundry',
                amountMinor: $intake->application_fee_minor,
                currency: $intake->application_fee_currency,
                tenderType: $data->tenderType,
                bankAccountId: $data->bankAccountId,
                targetAccountId: $data->incomeAccountId,
                receivedByUserId: $data->receivedByUserId,
            );

            $application->update([
                'application_fee_receipt_id' => $receipt->id,
                'status' => 'submitted',
            ]);

            return $application;
        });
    }
}
