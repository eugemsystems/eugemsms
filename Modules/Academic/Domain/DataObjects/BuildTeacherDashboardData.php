<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class BuildTeacherDashboardData
{
    public function __construct(
        public int $teacherStaffId,
        public int $termId,
    ) {}
}
