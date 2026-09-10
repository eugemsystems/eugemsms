<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\DataObjects\RevaluableAccountResult;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\JournalLine;

/**
 * Book B FIN-06 §3 "the two FX events that matter"/BR-FIN-06-011.
 * Shared by `RunFxRevaluationAction` (which posts the result) and
 * `SimulateRateChangeAction` (which only previews it) — one
 * computation, used both places, exactly the "one Action, tested once"
 * reasoning Volume 1 ADR-002 gives for the whole platform.
 *
 * Revaluable = every balance-sheet account (asset/liability; income and
 * expense are never revalued) carrying a non-zero balance in the given
 * foreign currency as at the given date. A control account (learner
 * debtors, supplier creditors) is revalued *per subledger entity*, not
 * in aggregate — a single restating line against the control account
 * would leave no way to say which learner's balance actually moved,
 * and every line touching a control account must carry a subledger
 * reference anyway (BR-FIN-01-009).
 */
final class RevaluationCalculator
{
    /**
     * @return array<int, RevaluableAccountResult>
     */
    public function compute(int $schoolId, Currency $baseCurrency, Currency $foreignCurrency, string $closingRate, CarbonInterface $asAt): array
    {
        $accounts = Account::withoutGlobalScopes()
            ->with('accountType')
            ->where('school_id', $schoolId)
            ->whereHas('accountType', fn ($q) => $q->whereIn('statement', ['balance_sheet']))
            ->get();

        $results = [];

        foreach ($accounts as $account) {
            $lines = JournalLine::withoutGlobalScopes()
                ->where('account_id', $account->id)
                ->where('currency', $foreignCurrency->value)
                ->where('effective_at', '<=', $asAt->copy()->endOfDay())
                ->get(['direction', 'amount_minor', 'base_amount_minor', 'subledger_type', 'subledger_id']);

            if ($lines->isEmpty()) {
                continue;
            }

            $isDebitNormal = $account->accountType->isDebitNormal();

            if ($account->is_control_account) {
                foreach ($lines->groupBy('subledger_id') as $subledgerId => $subledgerLines) {
                    $result = $this->resultFor(
                        $account, $isDebitNormal, $foreignCurrency, $baseCurrency, $closingRate, $subledgerLines,
                        subledgerType: $subledgerLines->first()->subledger_type,
                        subledgerId: (int) $subledgerId,
                    );

                    if ($result !== null) {
                        $results[] = $result;
                    }
                }

                continue;
            }

            $result = $this->resultFor($account, $isDebitNormal, $foreignCurrency, $baseCurrency, $closingRate, $lines);

            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @param  Collection<int, JournalLine>  $lines
     */
    private function resultFor(Account $account, bool $isDebitNormal, Currency $foreignCurrency, Currency $baseCurrency, string $closingRate, Collection $lines, ?string $subledgerType = null, ?int $subledgerId = null): ?RevaluableAccountResult
    {
        $debitForeign = (int) $lines->where('direction', 'DR')->sum('amount_minor');
        $creditForeign = (int) $lines->where('direction', 'CR')->sum('amount_minor');
        $foreignBalance = $isDebitNormal ? $debitForeign - $creditForeign : $creditForeign - $debitForeign;

        if ($foreignBalance === 0) {
            return null;
        }

        $debitBase = (int) $lines->where('direction', 'DR')->sum('base_amount_minor');
        $creditBase = (int) $lines->where('direction', 'CR')->sum('base_amount_minor');
        $recordedBase = $isDebitNormal ? $debitBase - $creditBase : $creditBase - $debitBase;

        $revaluedBase = Money::of($foreignBalance, $baseCurrency)->multiplyBy($closingRate)->minor;

        return new RevaluableAccountResult(
            accountId: $account->id,
            accountCode: $account->code,
            isDebitNormal: $isDebitNormal,
            foreignCurrency: $foreignCurrency->value,
            foreignBalanceMinor: $foreignBalance,
            recordedBaseMinor: $recordedBase,
            revaluedBaseMinor: $revaluedBase,
            subledgerType: $subledgerType,
            subledgerId: $subledgerId,
        );
    }
}
