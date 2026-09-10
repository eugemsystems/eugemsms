<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

final readonly class InitiateRolloverData
{
    public function __construct(
        public int $schoolId,
        public int $fromTermId,
        public int $toTermId,
        public int $initiatedByUserId,
    ) {}
}
