<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateCbtTestData
{
    /**
     * @param  array<string, mixed>|null  $assemblyRules  {count:int, mix:{easy:float,medium:float,hard:float}, topics?:array<int,string>}
     * @param  array<int, int>|null  $questionIds
     */
    public function __construct(
        public int $schoolId,
        public int $termId,
        public string $title,
        public int $subjectId,
        public int $durationMinutes,
        public CarbonInterface $opensAt,
        public CarbonInterface $closesAt,
        public int $createdByUserId,
        public string $assemblyMethod = 'manual',
        public ?array $assemblyRules = null,
        public ?array $questionIds = null,
        public bool $randomiseQuestionOrder = true,
        public bool $randomiseOptionOrder = true,
        public ?int $assessmentTypeId = null,
        public bool $browserFocusMonitoring = false,
        public ?int $maxTabSwitches = null,
    ) {}
}
