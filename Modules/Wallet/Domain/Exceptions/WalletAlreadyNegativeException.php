<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 FIN-14 §4/BR-FIN-14-013 (AC-FIN-14-005). A negative wallet
 * blocks further offline sales until settled — this is that block,
 * distinct from `InsufficientWalletBalanceException` (which refuses
 * an ONLINE sale outright rather than ever going negative at all).
 */
final class WalletAlreadyNegativeException extends DomainException
{
    public static function forWallet(int $walletId, int $balanceMinor): self
    {
        return new self(
            "Wallet [{$walletId}] is already negative ({$balanceMinor} minor units) and blocks further offline sales until settled.",
            ['wallet_id' => $walletId, 'balance_minor' => $balanceMinor],
        );
    }

    public function errorCode(): string
    {
        return 'WALLET_ALREADY_NEGATIVE';
    }
}
