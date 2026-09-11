<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateCourseSpaceData
{
    public function __construct(
        public int $teachingGroupId,
        public ?int $teacherStaffId = null,
        public ?int $bannerImageFileId = null,
    ) {}
}
