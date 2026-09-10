<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\CalculateAccountBalanceData;
use Modules\Finance\Domain\DataObjects\DeactivateAccountData;
use Modules\Finance\Domain\Events\AccountDeactivated;
use Modules\Finance\Domain\Exceptions\AccountHasBalanceException;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\JournalLine;

/**
 * ACT-DeactivateAccount (Book B FIN-01 §5/BR-FIN-01-020). Checks every
 * currency the account has ever carried a line in — a USD-only account
 * can still be blocked by a stray legacy ZWG balance from an import
 * error, and that's exactly the kind of thing this check exists to
 * surface rather than silently ignore.
 */
final class DeactivateAccountAction extends Action
{
    public function __construct(
        private readonly CalculateAccountBalanceAction $calculateBalance,
    ) {}

    public function execute(DeactivateAccountData $data): Account
    {
        $account = Account::findOrFail($data->accountId);

        $currencies = JournalLine::withoutGlobalScopes()
            ->where('account_id', $data->accountId)
            ->distinct()
            ->pluck('currency');

        foreach ($currencies as $currency) {
            $balance = $this->calculateBalance->execute(new CalculateAccountBalanceData(
                accountId: $data->accountId,
                currency: $currency,
                asAt: now(),
            ));

            if (! $balance->isZero()) {
                throw AccountHasBalanceException::forAccount($account->code);
            }
        }

        return $this->transaction(function () use ($account, $data): Account {
            $account->update([
                'is_active' => false,
                'closed_on' => now()->toDateString(),
                'updated_by' => $data->deactivatedByUserId,
            ]);

            event(new AccountDeactivated($account));

            return $account;
        });
    }
}
