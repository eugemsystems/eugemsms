<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class CreateGradeLevelData
{
    public function __construct(
        public int $schoolId,
        public int $sectionId,
        public string $code,
        public string $name,
        public int $ordinal,
        public bool $isExamLevel = false,
        public bool $isEntryLevel = false,
        public bool $isExitLevel = false,
        public ?int $capacity = null,
    ) {}
}
