<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\School;
use Modules\Finance\Domain\DataObjects\GenerateTrialBalanceData;
use Modules\Finance\Domain\DataObjects\TrialBalance;
use Modules\Finance\Domain\DataObjects\TrialBalanceLine;

/**
 * ACT-GenerateTrialBalance (Book B FIN-01 §5/BR-FIN-01-026). Always
 * computed from source `journal_lines` — never from `account_balances`
 * (that cache exists for fast listing screens; a trial balance is the
 * kind of figure a school acts on financially, so it always proves
 * itself from source, per §3's caching rules).
 */
final class GenerateTrialBalanceAction extends Action
{
    protected bool $transactional = false;

    public function execute(GenerateTrialBalanceData $data): TrialBalance
    {
        $school = School::withoutGlobalScopes()->findOrFail($data->schoolId);

        $query = DB::table('journal_lines')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.school_id', $data->schoolId)
            // See CalculateAccountBalanceAction for why this compares
            // against end-of-day rather than a bare date string.
            ->where('journal_lines.effective_at', '<=', $data->asAt->copy()->endOfDay());

        if ($data->termId !== null) {
            $query->where('journal_lines.term_id', $data->termId);
        }

        $perCurrencyRows = (clone $query)
            ->selectRaw('accounts.id as account_id, accounts.code as account_code, accounts.name as account_name, journal_lines.currency, '
                ."SUM(CASE WHEN journal_lines.direction = 'DR' THEN journal_lines.amount_minor ELSE 0 END) as debit_minor, "
                ."SUM(CASE WHEN journal_lines.direction = 'CR' THEN journal_lines.amount_minor ELSE 0 END) as credit_minor")
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'journal_lines.currency')
            ->get();

        $linesByCurrency = [];
        $totalsByCurrency = [];

        foreach ($perCurrencyRows as $row) {
            $currency = (string) $row->currency;

            $linesByCurrency[$currency] ??= [];
            $linesByCurrency[$currency][] = new TrialBalanceLine(
                accountId: (int) $row->account_id,
                accountCode: (string) $row->account_code,
                accountName: (string) $row->account_name,
                currency: $currency,
                debitMinor: (int) $row->debit_minor,
                creditMinor: (int) $row->credit_minor,
            );

            $totalsByCurrency[$currency] ??= ['debit_minor' => 0, 'credit_minor' => 0];
            $totalsByCurrency[$currency]['debit_minor'] += (int) $row->debit_minor;
            $totalsByCurrency[$currency]['credit_minor'] += (int) $row->credit_minor;
        }

        $baseRow = (clone $query)
            ->selectRaw("SUM(CASE WHEN journal_lines.direction = 'DR' THEN journal_lines.base_amount_minor ELSE 0 END) as debit_minor, "
                ."SUM(CASE WHEN journal_lines.direction = 'CR' THEN journal_lines.base_amount_minor ELSE 0 END) as credit_minor")
            ->first();

        return new TrialBalance(
            linesByCurrency: $linesByCurrency,
            totalsByCurrency: $totalsByCurrency,
            consolidatedBase: [
                'currency' => $school->base_currency,
                'debit_minor' => (int) ($baseRow->debit_minor ?? 0),
                'credit_minor' => (int) ($baseRow->credit_minor ?? 0),
            ],
        );
    }
}
