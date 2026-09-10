<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H1 FIN-09 §4/BR-FIN-09-005.
 */
final class InsufficientStockException extends DomainException
{
    public static function forItem(int $itemId, float $shortfall): self
    {
        return new self(
            "Insufficient stock for item #{$itemId} — short by {$shortfall}.",
            ['item_id' => $itemId, 'shortfall' => $shortfall],
        );
    }

    public function errorCode(): string
    {
        return 'INSUFFICIENT_STOCK';
    }
}
