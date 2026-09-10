<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class VerifyProjectData
{
    public function __construct(
        public int $learnerProjectId,
        public int $verifiedByUserId,
    ) {}
}
