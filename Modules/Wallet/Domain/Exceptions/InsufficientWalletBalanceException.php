<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 FIN-14 §4 — an ONLINE (connected) sale refuses outright
 * rather than go negative; only a sale that already happened offline
 * and is syncing against a now-known-insufficient true balance is
 * honoured negative, per `SyncOfflineWalletSaleAction`'s own
 * docblock and BR-FIN-14-012.
 */
final class InsufficientWalletBalanceException extends DomainException
{
    public static function forWallet(int $walletId, int $shortfallMinor): self
    {
        return new self(
            "Wallet [{$walletId}] has insufficient balance for this purchase — short by {$shortfallMinor} minor units.",
            ['wallet_id' => $walletId, 'shortfall_minor' => $shortfallMinor],
        );
    }

    public function errorCode(): string
    {
        return 'WALLET_INSUFFICIENT_BALANCE';
    }
}
