<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class LibraryClearanceResult
{
    /**
     * @param  array<int, int>  $outstandingLoanIds
     */
    public function __construct(
        public bool $isClear,
        public array $outstandingLoanIds,
    ) {}
}
