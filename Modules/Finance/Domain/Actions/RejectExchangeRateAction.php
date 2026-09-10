<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\DataObjects\RejectExchangeRateData;
use Modules\Finance\Models\ExchangeRate;

final class RejectExchangeRateAction extends Action
{
    public function execute(RejectExchangeRateData $data): ExchangeRate
    {
        $rate = ExchangeRate::findOrFail($data->exchangeRateId);

        if (! $rate->isPending()) {
            throw new InvalidStateTransitionException(
                "Only a pending rate can be rejected — this one is {$rate->status}.",
                ['exchange_rate_id' => $rate->id],
            );
        }

        return $this->transaction(function () use ($rate, $data): ExchangeRate {
            $rate->update([
                'status' => 'rejected',
                'approved_by' => $data->rejectedByUserId,
                'notes' => $data->reason,
            ]);

            return $rate;
        });
    }
}
