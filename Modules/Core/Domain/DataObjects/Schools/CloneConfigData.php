<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

/**
 * Copies one school's structural setup (sections, grade levels, houses)
 * into another — a time-saver for a tenant standing up its second or
 * subsequent school with the same shape. Never touches enrolment, staff,
 * or academic-year data.
 */
final readonly class CloneConfigData
{
    public function __construct(
        public int $sourceSchoolId,
        public int $targetSchoolId,
        public int $actingUserId,
        public bool $cloneSections = true,
        public bool $cloneGradeLevels = true,
        public bool $cloneHouses = true,
    ) {}
}
