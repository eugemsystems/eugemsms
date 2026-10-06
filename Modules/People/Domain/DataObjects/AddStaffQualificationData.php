<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class AddStaffQualificationData
{
    /**
     * @param  array<int, string>  $subjects  teaching subjects the qualification covers
     */
    public function __construct(
        public int $schoolId,
        public int $staffId,
        public string $qualificationType,
        public string $title,
        public string $institution,
        public string $country = 'ZW',
        public ?int $yearObtained = null,
        public ?string $gradeClass = null,
        public array $subjects = [],
        public ?int $certificateFileId = null,
    ) {}
}
