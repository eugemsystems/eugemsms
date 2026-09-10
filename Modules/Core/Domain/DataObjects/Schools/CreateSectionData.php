<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class CreateSectionData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $type,
        public int $sortOrder = 0,
        public ?int $headUserId = null,
    ) {}
}
