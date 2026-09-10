<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateSubjectPrerequisiteData
{
    public function __construct(
        public int $schoolId,
        public int $subjectId,
        public int $prerequisiteSubjectId,
        public string $severity,
        public ?string $minimumGrade = null,
        public ?string $examination = null,
    ) {}
}
