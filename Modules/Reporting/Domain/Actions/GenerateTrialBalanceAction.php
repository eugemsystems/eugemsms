<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\JournalLine;
use Modules\Reporting\Domain\DataObjects\GenerateTrialBalanceData;

/**
 * ACT-GenerateTrialBalance (Book H3 FIN-12 §3/BR-FIN-12-001/003
 * (AC-FIN-12-001)). Producible for any date, including a fully
 * closed and archived period — a pure query over `journal_lines`, so
 * it reproduces identically no matter when it's re-run.
 */
final class GenerateTrialBalanceAction extends Action
{
    /**
     * @return array<int, array{account_id: int, code: string, name: string, currency: string, debit_minor: int, credit_minor: int}>
     */
    public function execute(GenerateTrialBalanceData $data): array
    {
        $query = JournalLine::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->whereDate('effective_at', '<=', $data->asAt->toDateString());

        if ($data->asKnownOn !== null) {
            $query->whereHas('journal', fn ($q) => $q->where('posted_at', '<=', $data->asKnownOn));
        }

        $byAccountCurrency = $query->get()->groupBy(fn (JournalLine $line): string => "{$line->account_id}:{$line->currency}");

        if ($byAccountCurrency->isEmpty()) {
            return [];
        }

        $accountIds = $byAccountCurrency->keys()->map(fn (string $key): int => (int) explode(':', $key)[0])->unique();
        $accounts = Account::withoutGlobalScopes()->whereIn('id', $accountIds)->get()->keyBy('id');

        return $byAccountCurrency->map(function ($rows, string $key) use ($accounts): array {
            [$accountId, $currency] = explode(':', $key);
            $account = $accounts->get((int) $accountId);

            return [
                'account_id' => (int) $accountId,
                'code' => $account !== null ? $account->code : '',
                'name' => $account !== null ? $account->name : '',
                'currency' => $currency,
                'debit_minor' => (int) $rows->where('direction', 'DR')->sum('amount_minor'),
                'credit_minor' => (int) $rows->where('direction', 'CR')->sum('amount_minor'),
            ];
        })->values()->all();
    }
}
