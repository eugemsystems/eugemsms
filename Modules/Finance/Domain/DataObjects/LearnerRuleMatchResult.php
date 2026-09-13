<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class LearnerRuleMatchResult
{
    /**
     * @param  array<int, array{id: int, admission_number: string, name: string}>  $sample
     */
    public function __construct(
        public int $count,
        public array $sample,
    ) {}
}
