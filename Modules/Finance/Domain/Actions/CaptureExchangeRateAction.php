<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\CaptureExchangeRateData;
use Modules\Finance\Models\ExchangeRate;
use Modules\Finance\Models\ExchangeRateSource;

/**
 * ACT-CaptureExchangeRate (Book B FIN-06 §5/BR-FIN-06-004..006).
 * Never edits a rate in place — a correction is always a new row.
 * Overlapping the same pair+source auto-closes whichever *active*
 * rate was in effect at the new one's `effective_from`; a still-`pending`
 * rate awaiting approval isn't "in effect" yet, so it isn't touched by
 * this — it either gets approved or superseded by the approval flow.
 */
final class CaptureExchangeRateAction extends Action
{
    public function execute(CaptureExchangeRateData $data): ExchangeRate
    {
        $source = ExchangeRateSource::findOrFail($data->sourceId);

        return $this->transaction(function () use ($data, $source): ExchangeRate {
            ExchangeRate::withoutGlobalScopes()
                ->where('school_id', $data->schoolId)
                ->where('source_id', $source->id)
                ->where('from_currency', $data->fromCurrency)
                ->where('to_currency', $data->toCurrency)
                ->where('status', 'active')
                ->where('effective_from', '<', $data->effectiveFrom)
                ->update(['effective_to' => $data->effectiveFrom, 'status' => 'superseded']);

            $inverseRate = bcdiv('1', $data->rate, 10);

            return ExchangeRate::create([
                'school_id' => $data->schoolId,
                'source_id' => $source->id,
                'from_currency' => $data->fromCurrency,
                'to_currency' => $data->toCurrency,
                'rate' => $data->rate,
                'inverse_rate' => $inverseRate,
                'effective_from' => $data->effectiveFrom,
                'status' => $source->requires_approval ? 'pending' : 'active',
                'captured_by' => $data->capturedByUserId,
                'notes' => $data->notes,
                'created_at' => Carbon::now(),
            ]);
        });
    }
}
