<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

final readonly class TriggerRuleEvaluation
{
    public function __construct(
        public int $ruleId,
        public int $studentId,
        public int $suggestedSanctionTypeId,
        public bool $wasAppliedAutomatically,
    ) {}
}
