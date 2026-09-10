<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class LinenClearanceResult
{
    /**
     * @param  array<int, int>  $outstandingItemIds
     */
    public function __construct(
        public bool $isClear,
        public array $outstandingItemIds,
    ) {}
}
