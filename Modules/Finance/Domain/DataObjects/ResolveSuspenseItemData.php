<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Modules\Finance\Domain\Support\AllocationStrategy;

final readonly class ResolveSuspenseItemData
{
    public function __construct(
        public int $suspenseItemId,
        public int $studentId,
        public int $resolvedByUserId,
        public int $suspenseAccountId,
        public ?AllocationStrategy $allocationStrategy = null,
        public ?string $resolutionNote = null,
    ) {}
}
