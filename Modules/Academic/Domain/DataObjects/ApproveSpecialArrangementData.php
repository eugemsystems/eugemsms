<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ApproveSpecialArrangementData
{
    public function __construct(
        public int $arrangementId,
        public int $approvedByUserId,
    ) {}
}
