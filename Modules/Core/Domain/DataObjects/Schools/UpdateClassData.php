<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class UpdateClassData
{
    public function __construct(
        public int $schoolId,
        public int $classId,
        public string $code,
        public string $name,
        public int $capacity,
        public ?string $streamLabel = null,
        public ?int $classTeacherId = null,
        public ?int $assistantTeacherId = null,
        public ?int $roomId = null,
    ) {}
}
