<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class ConvertApplicationToStudentData
{
    public function __construct(
        public int $applicationId,
        public int $termId,
        public int $convertedByUserId,
        public int $creditBalanceAccountId,
        public bool $overrideCapacity = false,
        public ?string $overrideReason = null,
    ) {}
}
