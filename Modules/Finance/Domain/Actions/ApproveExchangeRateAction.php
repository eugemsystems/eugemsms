<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\DataObjects\ApproveExchangeRateData;
use Modules\Finance\Models\ExchangeRate;

/**
 * ACT-ApproveExchangeRate (Book B FIN-06 §5/BR-FIN-06-005). A pending
 * rate takes effect only here — approval is the moment it starts
 * superseding whatever was active for its pair, exactly like an
 * immediately-active capture does.
 */
final class ApproveExchangeRateAction extends Action
{
    public function execute(ApproveExchangeRateData $data): ExchangeRate
    {
        $rate = ExchangeRate::findOrFail($data->exchangeRateId);

        if (! $rate->isPending()) {
            throw new InvalidStateTransitionException(
                "Only a pending rate can be approved — this one is {$rate->status}.",
                ['exchange_rate_id' => $rate->id],
            );
        }

        return $this->transaction(function () use ($rate, $data): ExchangeRate {
            ExchangeRate::withoutGlobalScopes()
                ->where('school_id', $rate->school_id)
                ->where('source_id', $rate->source_id)
                ->where('from_currency', $rate->from_currency)
                ->where('to_currency', $rate->to_currency)
                ->where('status', 'active')
                ->where('effective_from', '<', $rate->effective_from)
                ->update(['effective_to' => $rate->effective_from, 'status' => 'superseded']);

            $rate->update([
                'status' => 'active',
                'approved_by' => $data->approvedByUserId,
                'approved_at' => Carbon::now(),
            ]);

            return $rate;
        });
    }
}
