<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class ImportZimsecResultsData
{
    /**
     * @param  array<int, array{candidate_number: string, subject_code: string, subject_name: string, grade: string, points?: string|null, is_provisional?: bool}>  $rows
     */
    public function __construct(
        public int $registrationId,
        public int $importedByUserId,
        public array $rows,
        public ?int $sourceFileId = null,
    ) {}
}
