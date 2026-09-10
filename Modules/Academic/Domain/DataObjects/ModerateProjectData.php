<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ModerateProjectData
{
    public function __construct(
        public int $learnerProjectId,
        public int $moderatorStaffId,
        public float $moderatedMark,
        public string $moderationNote,
    ) {}
}
