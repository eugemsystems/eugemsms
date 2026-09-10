<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

final readonly class VoidAllocatedNumberData
{
    public function __construct(
        public int $allocatedNumberId,
        public string $reason,
        public int $voidedByUserId,
    ) {}
}
