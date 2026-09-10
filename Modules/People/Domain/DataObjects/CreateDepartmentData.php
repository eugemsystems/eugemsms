<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class CreateDepartmentData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $type,
        public ?int $parentId = null,
        public ?int $costCentreId = null,
    ) {}
}
