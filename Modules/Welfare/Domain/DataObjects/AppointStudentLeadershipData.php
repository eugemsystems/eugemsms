<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AppointStudentLeadershipData
{
    /**
     * @param  array<int, string>|null  $grantedPermissions
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $studentId,
        public string $roleTitle,
        public CarbonInterface $startsOn,
        public int $appointedByUserId,
        public ?string $scopeType = null,
        public ?int $scopeId = null,
        public ?array $grantedPermissions = null,
    ) {}
}
