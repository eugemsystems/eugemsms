<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class CreateClassData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $gradeLevelId,
        public string $code,
        public string $name,
        public ?string $streamLabel = null,
        public ?int $classTeacherId = null,
        public ?int $assistantTeacherId = null,
        public ?int $roomId = null,
        public int $capacity = 40,
    ) {}
}
