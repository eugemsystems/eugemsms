<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class UpdateSectionData
{
    public function __construct(
        public int $schoolId,
        public int $sectionId,
        public string $code,
        public string $name,
        public string $type,
    ) {}
}
