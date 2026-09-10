<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Approvals;

final readonly class ApproveStepData
{
    public function __construct(
        public int $requestId,
        public int $actorUserId,
        public ?string $comment = null,
        public ?string $ip = null,
    ) {}
}
