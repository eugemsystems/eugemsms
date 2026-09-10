<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ReturnIssuedItemData
{
    public function __construct(
        public int $learnerIssuedItemId,
        public string $conditionAtReturn,
        public int $receivedByUserId,
        public CarbonInterface $returnedOn,
        public ?string $notes = null,
    ) {}
}
