<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Wallet\Domain\DataObjects\CloseWalletData;
use Modules\Wallet\Domain\DataObjects\ProcessTermEndWalletData;
use Modules\Wallet\Models\StudentWallet;

/**
 * ACT-CloseWallet (Book H3 FIN-14 §5/BR-FIN-14-016). On withdrawal or
 * graduation, any balance is refunded or transferred to the fee
 * account — the same real money-movement `ProcessTermEndWalletAction`
 * already does (reused directly, not re-implemented); `carry_forward`
 * makes no sense for a wallet that's closing, so it isn't accepted
 * here.
 */
final class CloseWalletAction extends Action
{
    public function __construct(
        private readonly ProcessTermEndWalletAction $processTermEnd,
    ) {}

    public function execute(CloseWalletData $data): StudentWallet
    {
        if (! in_array($data->policy, ['refund', 'transfer_to_fees'], true)) {
            throw ValidationException::withMessages(['policy' => 'A closing wallet must be refunded or transferred to fees.']);
        }

        $wallet = $this->processTermEnd->execute(new ProcessTermEndWalletData(
            walletId: $data->walletId,
            academicYearId: $data->academicYearId,
            termId: $data->termId,
            policy: $data->policy,
            performedByUserId: $data->performedByUserId,
            refundClearingAccountId: $data->refundClearingAccountId,
            feeDebtorsAccountId: $data->feeDebtorsAccountId,
        ));

        return $this->transaction(fn (): StudentWallet => tap($wallet)->update(['status' => 'closed']));
    }
}
