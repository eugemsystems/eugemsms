<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Approvals;

use Carbon\CarbonInterface;

final readonly class CreateDelegationData
{
    public function __construct(
        public int $schoolId,
        public int $delegatorId,
        public int $delegateId,
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
        public ?string $approvableType = null,
        public ?string $reason = null,
        public ?int $createdByUserId = null,
    ) {}
}
