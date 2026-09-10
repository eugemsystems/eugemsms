<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Imports;

final readonly class CreateImportBatchData
{
    /**
     * @param  array<string, string>  $columnMapping  mapped column => source header
     */
    public function __construct(
        public int $schoolId,
        public string $definitionKey,
        public int $sourceFileId,
        public array $columnMapping,
        public int $importedByUserId,
        public string $duplicateStrategy = 'skip',
        public bool $dryRun = false,
        public ?int $academicYearId = null,
        public ?int $termId = null,
    ) {}
}
