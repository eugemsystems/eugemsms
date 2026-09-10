<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Contracts\CurrencyConverter;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\Exceptions\AccountNotPostableException;
use Modules\Finance\Domain\Exceptions\CrossSchoolAccountException;
use Modules\Finance\Domain\Exceptions\CurrencyRestrictedAccountException;
use Modules\Finance\Domain\Exceptions\FutureDatedJournalException;
use Modules\Finance\Domain\Exceptions\MissingCostCentreException;
use Modules\Finance\Domain\Exceptions\MissingSubledgerException;
use Modules\Finance\Domain\Exceptions\RoundingToleranceExceededException;
use Modules\Finance\Domain\Exceptions\UnbalancedJournalException;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CurrencyConversion;
use Modules\Finance\Models\ExchangeRate;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\JournalLine;
use RuntimeException;

/**
 * Book B FIN-01 §6 ⭐ — the posting engine's structural and balance
 * validation, FX conversion, numbering, and persistence. Shared by
 * `PostJournalAction`, `CreateManualJournalAction`, and
 * `ImportOpeningBalancesAction`: all three assemble and persist a
 * journal identically and differ only in what `status` they land at and
 * what happens immediately after (synchronous audit + cache for a
 * posted journal; nothing yet for a draft). Not an `Action` itself —
 * it's an internal step, never called directly by a Livewire component
 * or controller.
 */
final class JournalAssembler
{
    public function __construct(
        private readonly CurrencyConverter $fx,
        private readonly AllocateNumberAction $allocateNumber,
        private readonly SettingResolver $settings,
    ) {}

    public function assemble(PostJournalData $data, string $status): Journal
    {
        $this->assertAtLeastTwoLines($data->lines);

        $accounts = $this->loadAndValidateAccounts($data);

        $this->assertNotTooFarFuture($data);

        $this->assertBalancedPerCurrency($data->lines);

        $school = School::withoutGlobalScopes()->findOrFail($data->schoolId);
        $baseCurrency = Currency::from($school->base_currency);

        $term = Term::withoutGlobalScopes()->findOrFail($data->termId);
        $termState = $term->stateFor(PeriodType::Financial);

        $probe = new Journal(['term_id' => $data->termId, 'academic_year_id' => $data->academicYearId]);
        $probe->periodOverrideApproved = $data->overrideSoftClose;
        PeriodGuard::assertWritable($probe);

        $lineRows = $this->convertAndBuildLines($data, $accounts, $baseCurrency);

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'journal',
            allocatedByUserId: $data->postedByUserId,
            academicYearId: $data->academicYearId,
            termId: $data->termId,
        ));

        $journal = new Journal([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'journal_number' => $number->formatted_number,
            'journal_type' => $data->journalType,
            'narration' => $data->narration,
            'reference' => $data->reference,
            'source_type' => $data->sourceType,
            'source_id' => $data->sourceId,
            'effective_at' => $data->effectiveAt->toDateString(),
            'posted_at' => Carbon::now(),
            'is_prior_period_adjustment' => $termState !== PeriodState::Open,
            'is_reversal' => $data->isReversal,
            'reverses_journal_id' => $data->reversesJournalId,
            'reversal_reason' => $data->reversalReason,
            'status' => $status,
            'posted_by' => $data->postedByUserId,
            'batch_uuid' => $data->batchUuid,
            'created_at' => Carbon::now(),
        ]);
        $journal->periodOverrideApproved = $data->overrideSoftClose;
        $journal->save();

        $createdLines = $journal->lines()->createMany($lineRows);

        $this->recordConversionAudits($data->schoolId, $createdLines);

        return $journal->load('lines');
    }

    /**
     * BR-FIN-06-003: every real conversion (never a same-currency,
     * rate-1 no-op) is recorded so it stays re-derivable and
     * challengeable — an amount converted between USD and ZWG on a
     * receipt is exactly the kind of figure a parent may query.
     *
     * @param  Collection<int, JournalLine>  $lines
     */
    private function recordConversionAudits(int $schoolId, Collection $lines): void
    {
        $now = Carbon::now();
        $rateIds = $lines->pluck('exchange_rate_id')->filter()->unique();
        $rates = ExchangeRate::withoutGlobalScopes()->whereIn('id', $rateIds)->get(['id', 'effective_from'])->keyBy('id');

        foreach ($lines as $line) {
            if ($line->exchange_rate_id === null) {
                continue;
            }

            $rate = $rates->get($line->exchange_rate_id);

            CurrencyConversion::create([
                'school_id' => $schoolId,
                'journal_line_id' => $line->id,
                'context_type' => 'journal_line',
                'context_id' => $line->id,
                'from_currency' => $line->currency,
                'from_amount_minor' => $line->amount_minor,
                'to_currency' => $line->base_currency,
                'to_amount_minor' => $line->base_amount_minor,
                'exchange_rate_id' => $line->exchange_rate_id,
                'rate_used' => $line->exchange_rate,
                'rate_effective_from' => $rate !== null ? $rate->effective_from : $now,
                'converted_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<int, JournalLineData>  $lines
     */
    private function assertAtLeastTwoLines(array $lines): void
    {
        if (count($lines) < 2) {
            throw new RuntimeException('A journal requires at least two lines (BR-FIN-01-002).');
        }
    }

    /**
     * @return array<int, Account>
     */
    private function loadAndValidateAccounts(PostJournalData $data): array
    {
        $accountIds = collect($data->lines)->pluck('accountId')->unique()->all();
        $accounts = Account::withoutGlobalScopes()->whereIn('id', $accountIds)->get()->keyBy('id');

        foreach ($data->lines as $lineData) {
            $account = $accounts->get($lineData->accountId);

            if ($account === null || $account->school_id !== $data->schoolId) {
                throw CrossSchoolAccountException::forAccount($lineData->accountId);
            }

            if (! $account->is_postable) {
                throw AccountNotPostableException::forAccount($account->code);
            }

            if ($account->requires_cost_centre && $lineData->costCentreId === null) {
                throw MissingCostCentreException::forAccount($account->code);
            }

            if ($account->is_control_account && ($lineData->subledgerType === null || $lineData->subledgerId === null)) {
                throw MissingSubledgerException::forAccount($account->code);
            }

            if ($account->currency !== null && $account->currency !== $lineData->amount->currency->value) {
                throw CurrencyRestrictedAccountException::forAccount($account->code, $account->currency, $lineData->amount->currency->value);
            }
        }

        return $accounts->all();
    }

    private function assertNotTooFarFuture(PostJournalData $data): void
    {
        $maxFutureDays = (int) $this->settings->get('finance.max_future_dating_days', new ScopeChain(schoolId: $data->schoolId));
        $latestAllowed = Carbon::now()->addDays($maxFutureDays)->endOfDay();

        if ($data->effectiveAt->greaterThan($latestAllowed)) {
            throw FutureDatedJournalException::forDate($data->effectiveAt->toDateString(), $maxFutureDays);
        }
    }

    /**
     * @param  array<int, JournalLineData>  $lines
     */
    private function assertBalancedPerCurrency(array $lines): void
    {
        $byCurrency = collect($lines)->groupBy(fn (JournalLineData $line): string => $line->amount->currency->value);

        foreach ($byCurrency as $currency => $currencyLines) {
            $debit = $currencyLines->where('direction', 'DR')->sum(fn (JournalLineData $l): int => $l->amount->minor);
            $credit = $currencyLines->where('direction', 'CR')->sum(fn (JournalLineData $l): int => $l->amount->minor);

            if ($debit !== $credit) {
                throw UnbalancedJournalException::forCurrency((string) $currency, $debit, $credit);
            }
        }
    }

    /**
     * @param  array<int, Account>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function convertAndBuildLines(PostJournalData $data, array $accounts, Currency $baseCurrency): array
    {
        $rows = [];
        $baseDebit = 0;
        $baseCredit = 0;
        $lineNumber = 1;

        foreach ($data->lines as $lineData) {
            $converted = $this->fx->convert($lineData->amount, $baseCurrency, $data->effectiveAt, $data->schoolId);

            if ($lineData->direction === 'DR') {
                $baseDebit += $converted->amount->minor;
            } else {
                $baseCredit += $converted->amount->minor;
            }

            $rows[] = [
                'school_id' => $data->schoolId,
                'line_number' => $lineNumber++,
                'account_id' => $lineData->accountId,
                'cost_centre_id' => $lineData->costCentreId,
                'direction' => $lineData->direction,
                'amount_minor' => $lineData->amount->minor,
                'currency' => $lineData->amount->currency->value,
                'base_amount_minor' => $converted->amount->minor,
                'base_currency' => $baseCurrency->value,
                'exchange_rate' => $converted->rate,
                'exchange_rate_id' => $converted->exchangeRateId,
                'subledger_type' => $lineData->subledgerType,
                'subledger_id' => $lineData->subledgerId,
                'narration' => $lineData->narration,
                'effective_at' => $data->effectiveAt->toDateString(),
                'term_id' => $data->termId,
                'created_at' => Carbon::now(),
            ];
        }

        $residual = $baseDebit - $baseCredit;

        if ($residual !== 0) {
            $tolerance = (int) $this->settings->get('finance.rounding_tolerance_minor', new ScopeChain(schoolId: $data->schoolId));

            if (abs($residual) > $tolerance) {
                throw RoundingToleranceExceededException::forResidual($residual, $tolerance);
            }

            $roundingAccount = $this->resolveSystemAccount($data->schoolId, 'rounding');

            $rows[] = [
                'school_id' => $data->schoolId,
                'line_number' => $lineNumber,
                'account_id' => $roundingAccount->id,
                'cost_centre_id' => null,
                // residual > 0 means base debits exceed base credits — the
                // rounding line adds the missing credit, and vice versa.
                'direction' => $residual > 0 ? 'CR' : 'DR',
                'amount_minor' => abs($residual),
                'currency' => $baseCurrency->value,
                'base_amount_minor' => abs($residual),
                'base_currency' => $baseCurrency->value,
                'exchange_rate' => '1.0000000000',
                'exchange_rate_id' => null,
                'subledger_type' => null,
                'subledger_id' => null,
                'narration' => 'Base-currency rounding residual',
                'effective_at' => $data->effectiveAt->toDateString(),
                'term_id' => $data->termId,
                'created_at' => Carbon::now(),
            ];
        }

        return $rows;
    }

    private function resolveSystemAccount(int $schoolId, string $systemKey): Account
    {
        return Account::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('system_key', $systemKey)
            ->firstOrFail();
    }
}
