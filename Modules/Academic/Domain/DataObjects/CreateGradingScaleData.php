<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateGradingScaleData
{
    /**
     * @param  array<int, GradeBandInput>  $bands
     * @param  array<int, int>|null  $isDefaultForLevel
     */
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $scaleType,
        public array $bands,
        public ?int $frameworkId = null,
        public bool $lowerIsBetter = false,
        public ?string $passGrade = null,
        public ?array $isDefaultForLevel = null,
    ) {}
}
