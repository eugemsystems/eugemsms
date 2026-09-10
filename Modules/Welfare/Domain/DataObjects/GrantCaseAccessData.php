<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

final readonly class GrantCaseAccessData
{
    public function __construct(
        public int $schoolId,
        public int $caseId,
        public int $userId,
        public string $accessLevel,
        public int $grantedByUserId,
        public string $reason,
        public ?int $expiryDays = null,
    ) {}
}
