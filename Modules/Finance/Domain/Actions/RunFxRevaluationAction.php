<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Contracts\CurrencyConverter;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\DataObjects\RunFxRevaluationData;
use Modules\Finance\Domain\Exceptions\NoExchangeRateException;
use Modules\Finance\Domain\Support\RevaluationCalculator;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\FxRevaluation;

/**
 * ACT-RunFxRevaluation (Book B FIN-06 §3 "unrealised FX"/§5
 * BR-FIN-06-010..012). Restates every revaluable account to the
 * closing rate at period end — "the step most systems skip", and
 * skipping it silently overstates the balance sheet term after term.
 * A period's financial close checklist item is satisfied by this
 * having *run* (even with nothing to revalue), not merely existing.
 */
final class RunFxRevaluationAction extends Action
{
    public function __construct(
        private readonly RevaluationCalculator $calculator,
        private readonly CurrencyConverter $fx,
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(RunFxRevaluationData $data): FxRevaluation
    {
        $school = School::withoutGlobalScopes()->findOrFail($data->schoolId);
        $baseCurrency = Currency::from($school->base_currency);

        $accountsRevalued = [];
        $gainMinor = 0;
        $lossMinor = 0;
        $lines = [];
        $lastRateId = null;

        foreach (Currency::cases() as $foreign) {
            if ($foreign === $baseCurrency) {
                continue;
            }

            try {
                $zero = $this->fx->convert(Money::zero($foreign), $baseCurrency, $data->revaluationDate, $data->schoolId);
            } catch (NoExchangeRateException) {
                // No rate for this pair at all — and FIN-01 would already
                // have refused to post any line in it, so there is
                // nothing to revalue either.
                continue;
            }

            $lastRateId = $zero->exchangeRateId;

            foreach ($this->calculator->compute($data->schoolId, $baseCurrency, $foreign, $zero->rate, $data->revaluationDate) as $result) {
                $diff = $result->differenceMinor();

                if ($diff === 0) {
                    continue;
                }

                $accountDirection = $result->isDebitNormal
                    ? ($diff > 0 ? 'DR' : 'CR')
                    : ($diff > 0 ? 'CR' : 'DR');

                $lines[] = new JournalLineData(
                    $result->accountId,
                    $accountDirection,
                    Money::of(abs($diff), $baseCurrency),
                    subledgerType: $result->subledgerType,
                    subledgerId: $result->subledgerId,
                );

                $pnl = $result->pnlImpactMinor();

                if ($pnl > 0) {
                    $gainMinor += $pnl;
                } else {
                    $lossMinor += abs($pnl);
                }

                $accountsRevalued[] = [
                    'account_id' => $result->accountId,
                    'account_code' => $result->accountCode,
                    'foreign_currency' => $result->foreignCurrency,
                    'foreign_balance_minor' => $result->foreignBalanceMinor,
                    'recorded_base_minor' => $result->recordedBaseMinor,
                    'revalued_base_minor' => $result->revaluedBaseMinor,
                ];
            }
        }

        $journal = null;

        if ($gainMinor > 0 || $lossMinor > 0) {
            if ($gainMinor > 0) {
                $lines[] = new JournalLineData($this->resolveAccount($data->schoolId, 'fx_unrealised_gain')->id, 'CR', Money::of($gainMinor, $baseCurrency));
            }

            if ($lossMinor > 0) {
                $lines[] = new JournalLineData($this->resolveAccount($data->schoolId, 'fx_unrealised_loss')->id, 'DR', Money::of($lossMinor, $baseCurrency));
            }

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'FX_REVALUATION',
                narration: "FX revaluation as at {$data->revaluationDate->toDateString()}",
                lines: $lines,
                effectiveAt: $data->revaluationDate,
                postedByUserId: $data->performedByUserId,
            ));
        }

        return $this->transaction(fn (): FxRevaluation => FxRevaluation::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'revaluation_date' => $data->revaluationDate->toDateString(),
            'closing_rate_id' => $lastRateId,
            'accounts_revalued' => $accountsRevalued,
            'gain_minor' => $gainMinor,
            'loss_minor' => $lossMinor,
            'base_currency' => $baseCurrency->value,
            'journal_id' => $journal?->id,
            'status' => 'posted',
            'performed_by' => $data->performedByUserId,
            'performed_at' => Carbon::now(),
        ]));
    }

    private function resolveAccount(int $schoolId, string $systemKey): Account
    {
        return Account::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('system_key', $systemKey)
            ->firstOrFail();
    }
}
