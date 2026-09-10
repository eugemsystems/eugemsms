<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H2 OPS-02 §3/BR-OPS-02-004. A work order whose estimated cost
 * meets or exceeds `maintenance.work_order_approval_threshold_minor`
 * requires both approval and a budget line to check against — this
 * fires when the estimate is above the threshold but no budget line
 * was supplied.
 */
class WorkOrderRequiresBudgetLineException extends DomainException
{
    public static function aboveThreshold(int $estimatedCostMinor, int $thresholdMinor): self
    {
        return new self(
            "This work order's estimated cost of {$estimatedCostMinor} minor units meets or exceeds the {$thresholdMinor} approval threshold and requires a budget line.",
            ['estimated_cost_minor' => $estimatedCostMinor, 'threshold_minor' => $thresholdMinor],
        );
    }

    public function errorCode(): string
    {
        return 'WORK_ORDER_REQUIRES_BUDGET_LINE';
    }
}
