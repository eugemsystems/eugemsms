<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Contracts\CurrencyConverter;
use Modules\Finance\Domain\DataObjects\CalculateRealisedFxData;
use Modules\Finance\Domain\DataObjects\RealisedFxResult;

/**
 * ACT-CalculateRealisedFx (Book B FIN-06 §3 "the two FX events that
 * matter"/BR-FIN-06-009). Converts the tendered amount at the
 * settlement-date rate and compares it to what was actually relieved
 * off the obligation — the difference is the realised gain or loss
 * that a settling module (FIN-04's receipting) posts in the same
 * journal as the settlement itself, never as a separate entry.
 *
 * This is the calculation primitive only. §3's worked example shows a
 * bank-side foreign-currency debit with no matching foreign-currency
 * credit, which cannot itself satisfy BR-FIN-01-003's "balances within
 * each currency independently" — a real settlement needs an
 * intermediate foreign-currency leg (e.g. a suspense/clearing account)
 * that only FIN-04's actual receipt-and-allocation flow can model
 * correctly. Posting the fully-balanced settlement journal is FIN-04's
 * responsibility; this action only proves the gain/loss figure.
 */
final class CalculateRealisedFxAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly CurrencyConverter $fx,
    ) {}

    public function execute(CalculateRealisedFxData $data): RealisedFxResult
    {
        $school = School::withoutGlobalScopes()->findOrFail($data->schoolId);
        $baseCurrency = Currency::from($school->base_currency);

        $converted = $this->fx->convert($data->tenderedAmount, $baseCurrency, $data->settlementDate, $data->schoolId);

        return new RealisedFxResult(
            bankBaseAmount: $converted->amount,
            obligationReliefAmount: $data->obligationReliefAmount,
            exchangeRateId: $converted->exchangeRateId,
        );
    }
}
