<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class AddObservationTeacherCommentData
{
    public function __construct(
        public int $observationId,
        public int $commentingStaffId,
        public string $comments,
        public bool $acknowledged = true,
    ) {}
}
