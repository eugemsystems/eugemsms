<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class ZimsecPassRateResult
{
    /**
     * @param  array<int, array{subject_code: string, subject_name: string, candidates: int, passes: int, pass_rate: float}>  $bySubject
     * @param  array<int, array{class_id: int|null, candidates: int, passes: int, pass_rate: float}>  $byClass
     * @param  array<int, array{exam_series: string, candidates: int, passes: int, pass_rate: float}>  $historical
     */
    public function __construct(
        public array $bySubject,
        public array $byClass,
        public array $historical,
    ) {}
}
