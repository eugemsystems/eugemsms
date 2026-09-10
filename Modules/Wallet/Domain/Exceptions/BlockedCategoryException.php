<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 FIN-14 §5/BR-FIN-14-005 (AC-FIN-14-002). Refused with the
 * reason shown to the operator, not silently omitted from the sale.
 */
final class BlockedCategoryException extends DomainException
{
    public static function forCategory(string $category, string $productName): self
    {
        return new self(
            "{$productName} is in the blocked category [{$category}] for this learner's wallet.",
            ['category' => $category, 'product_name' => $productName],
        );
    }

    public function errorCode(): string
    {
        return 'WALLET_BLOCKED_CATEGORY';
    }
}
