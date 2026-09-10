<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\AssertLedgerBalancedData;
use Modules\Finance\Domain\DataObjects\BalanceAssertion;
use Modules\Finance\Domain\DataObjects\GenerateTrialBalanceData;
use Modules\Finance\Domain\Events\TrialBalanceImbalanceDetected;

/**
 * ACT-AssertLedgerBalanced (Book B FIN-01 §5/§13 JOB-AssertTrialBalance).
 * Invariant I-1: "for every school, every currency, every point in
 * time: Σ debits = Σ credits." Used by both the financial close
 * checklist and the nightly integrity job.
 */
final class AssertLedgerBalancedAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly GenerateTrialBalanceAction $generateTrialBalance,
    ) {}

    public function execute(AssertLedgerBalancedData $data): BalanceAssertion
    {
        $trialBalance = $this->generateTrialBalance->execute(new GenerateTrialBalanceData(
            schoolId: $data->schoolId,
            asAt: Carbon::now(),
            termId: $data->termId,
        ));

        $balanced = $trialBalance->isBalanced();

        if (! $balanced) {
            foreach ($trialBalance->totalsByCurrency as $currency => $totals) {
                if ($totals['debit_minor'] !== $totals['credit_minor']) {
                    event(new TrialBalanceImbalanceDetected(
                        schoolId: $data->schoolId,
                        currency: $currency,
                        debitMinor: $totals['debit_minor'],
                        creditMinor: $totals['credit_minor'],
                    ));
                }
            }
        }

        return new BalanceAssertion($balanced, $trialBalance);
    }
}
