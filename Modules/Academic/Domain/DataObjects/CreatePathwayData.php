<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreatePathwayData
{
    public function __construct(
        public int $schoolId,
        public int $frameworkId,
        public string $code,
        public string $name,
        public int $appliesFromLevelOrdinal,
        public ?string $description = null,
        public bool $isDefault = false,
    ) {}
}
