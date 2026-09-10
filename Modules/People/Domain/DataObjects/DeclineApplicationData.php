<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class DeclineApplicationData
{
    public function __construct(
        public int $applicationId,
        public string $reason,
        public int $declinedByUserId,
    ) {}
}
