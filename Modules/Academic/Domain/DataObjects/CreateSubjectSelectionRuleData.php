<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateSubjectSelectionRuleData
{
    /**
     * @param  array<int, int>|null  $subjectIds
     */
    public function __construct(
        public int $schoolId,
        public int $frameworkId,
        public string $ruleType,
        public string $severity,
        public string $message,
        public ?int $gradeLevelId = null,
        public ?string $pathway = null,
        public ?int $subjectGroupId = null,
        public ?array $subjectIds = null,
        public ?int $minCount = null,
        public ?int $maxCount = null,
        public ?string $sourceReference = null,
        public bool $requiresConfirmation = false,
    ) {}
}
