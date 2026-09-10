<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class ActivateFeeStructureData
{
    public function __construct(
        public int $structureId,
        public int $activatedByUserId,
    ) {}
}
