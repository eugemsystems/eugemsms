<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

final readonly class SwitchSessionData
{
    public function __construct(
        public int $userId,
        public int $schoolId,
        public int $academicYearId,
        public ?int $termId = null,
    ) {}
}
