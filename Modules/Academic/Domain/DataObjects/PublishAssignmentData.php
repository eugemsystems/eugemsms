<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class PublishAssignmentData
{
    public function __construct(
        public int $assignmentId,
    ) {}
}
