<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\StudentGuardian;
use Modules\Wallet\Domain\DataObjects\SetWalletControlsData;
use Modules\Wallet\Domain\Exceptions\WalletControlRightRequiredException;
use Modules\Wallet\Models\StudentWallet;

/**
 * ACT-SetWalletControls (Book H3 FIN-14 §5/BR-FIN-14-004). Spending
 * controls are set by a guardian holding `is_fee_responsible` — the
 * spec's "or an explicit wallet-control right" alternative isn't
 * modelled yet (see `WalletControlRightRequiredException`'s own
 * docblock), so this is the sole gate.
 */
final class SetWalletControlsAction extends Action
{
    public function execute(SetWalletControlsData $data): StudentWallet
    {
        $wallet = StudentWallet::findOrFail($data->walletId);

        $link = StudentGuardian::where('student_id', $wallet->student_id)
            ->where('guardian_id', $data->setByGuardianId)
            ->where('status', 'active')
            ->where('is_fee_responsible', true)
            ->first();

        if ($link === null) {
            throw WalletControlRightRequiredException::forGuardian($data->setByGuardianId, $wallet->student_id);
        }

        return $this->transaction(fn (): StudentWallet => tap($wallet)->update([
            'daily_limit_minor' => $data->dailyLimitMinor,
            'weekly_limit_minor' => $data->weeklyLimitMinor,
            'per_transaction_limit_minor' => $data->perTransactionLimitMinor,
            'blocked_categories' => $data->blockedCategories,
            'low_balance_threshold_minor' => $data->lowBalanceThresholdMinor,
            'auto_topup_enabled' => $data->autoTopupEnabled,
            'auto_topup_amount_minor' => $data->autoTopupAmountMinor,
            'controls_set_by' => $data->setByGuardianId,
            'controls_updated_at' => Carbon::now(),
        ]));
    }
}
