<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-10 §4/BR-ACA-10-009. A stock-take can only be completed
 * (and copies marked lost) once a second, confirmatory scanning pass
 * has actually happened — never off a single pass, which is far more
 * prone to a scanning miss than a genuinely missing copy.
 */
class StockTakeNotReadyException extends DomainException
{
    public static function forStockTake(int $stockTakeId): self
    {
        return new self(
            "Stock-take #{$stockTakeId} cannot be completed before its confirmatory second pass.",
            ['stock_take_id' => $stockTakeId],
        );
    }

    public function errorCode(): string
    {
        return 'LIBRARY_STOCK_TAKE_NOT_READY';
    }
}
