<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class DeactivateFeeLiabilityData
{
    public function __construct(
        public int $feeLiabilityId,
    ) {}
}
