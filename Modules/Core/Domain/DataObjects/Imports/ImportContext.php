<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Imports;

final readonly class ImportContext
{
    public function __construct(
        public int $schoolId,
        public int $batchId,
        public string $duplicateStrategy,
        public int $importedByUserId,
        public ?int $academicYearId = null,
        public ?int $termId = null,
    ) {}
}
