<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book I COM-02 §4 ⭐/BR-COM-02-008 (AC-COM-02-005). A rule is never
 * switched on blind.
 */
final class CostEstimateNotReviewedException extends DomainException
{
    public static function forRule(int $ruleId): self
    {
        return new self(
            "Rule #{$ruleId} cannot be activated: its estimated monthly cost has not been reviewed.",
            ['rule_id' => $ruleId],
        );
    }

    public function errorCode(): string
    {
        return 'COST_ESTIMATE_NOT_REVIEWED';
    }
}
