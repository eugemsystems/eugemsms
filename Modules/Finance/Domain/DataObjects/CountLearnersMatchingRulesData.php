<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CountLearnersMatchingRulesData
{
    /**
     * @param  array<int, array{attribute: string, operator: string, value: mixed}>  $rules
     */
    public function __construct(
        public int $schoolId,
        public int $termId,
        public array $rules,
    ) {}
}
