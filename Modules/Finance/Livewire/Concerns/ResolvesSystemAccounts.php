<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Concerns;

use Modules\Finance\Models\Account;

/**
 * Book B FIN-04's own domain actions (`CreateReceiptAction`,
 * `ResolveSuspenseItemAction`, `ClearChequeAction`,
 * `RevealAndCloseTillSessionAction`) deliberately take every GL account
 * decision as an explicit parameter — "no system-account
 * auto-provisioning" (see those actions' own docblocks). That is
 * correct for the domain layer, but a cashier cannot be asked to pick
 * "which account is Suspense" on every single receipt and still meet
 * BR-FIN-04-023's 20-second transaction budget. This trait bridges the
 * two: the UI resolves a school's own configured system account by
 * `Account.system_key` (set once via `Finance\Accounts\Editor`, the
 * same mechanism FIN-01/06 already use for `rounding`/
 * `fx_unrealised_gain`/`fx_unrealised_loss`), and surfaces a clear,
 * actionable error when a school hasn't configured one yet, rather
 * than silently guessing an account.
 */
trait ResolvesSystemAccounts
{
    protected function systemAccount(string $key): ?Account
    {
        return Account::where('system_key', $key)->where('is_active', true)->first();
    }

    protected function requireSystemAccount(string $key, string $label): int
    {
        $account = $this->systemAccount($key);

        if ($account === null) {
            abort(422, __('No active :label account is configured (system key ":key"). Set one up in the Chart of Accounts first.', ['label' => $label, 'key' => $key]));
        }

        return $account->id;
    }
}
