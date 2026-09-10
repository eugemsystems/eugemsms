<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class ReleaseFinalPayData
{
    public function __construct(
        public int $checklistId,
        public int $releasedByUserId,
    ) {}
}
