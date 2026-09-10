<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordCaseEntryData
{
    /**
     * @param  array<int, string>|null  $presentPersons
     * @param  array<int, int>|null  $attachmentFileIds
     */
    public function __construct(
        public int $schoolId,
        public int $caseId,
        public string $entryType,
        public CarbonInterface $entryAt,
        public string $content,
        public int $recordedByUserId,
        public bool $isLearnerAccount = false,
        public ?array $presentPersons = null,
        public ?array $attachmentFileIds = null,
    ) {}
}
