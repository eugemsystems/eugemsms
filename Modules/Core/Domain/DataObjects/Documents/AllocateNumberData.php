<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

final readonly class AllocateNumberData
{
    public function __construct(
        public int $schoolId,
        public string $documentType,
        public int $allocatedByUserId,
        public ?int $academicYearId = null,
        public ?int $termId = null,
        public ?string $documentableType = null,
        public ?int $documentableId = null,
    ) {}
}
