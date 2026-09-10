<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateSyllabusData
{
    /**
     * @param  array<string, mixed>|null  $topics
     */
    public function __construct(
        public int $schoolId,
        public int $subjectId,
        public int $frameworkId,
        public string $title,
        public ?int $gradeLevelId = null,
        public ?string $version = null,
        public ?CarbonInterface $effectiveFrom = null,
        public ?CarbonInterface $effectiveTo = null,
        public ?int $fileId = null,
        public ?array $topics = null,
    ) {}
}
