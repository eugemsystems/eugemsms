<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateSubjectGroupData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public ?string $description = null,
        public bool $requiresLaboratory = false,
        public bool $requiresWorkshop = false,
        public ?int $sortOrder = null,
    ) {}
}
