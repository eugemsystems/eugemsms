<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book B FIN-02 §6/BR-FIN-02-019. An ad hoc charge above
 * `finance.ad_hoc_approval_threshold_minor` needs `approved_by` before
 * it may be raised.
 */
class AdHocChargeRequiresApprovalException extends DomainException
{
    public static function aboveThreshold(int $amountMinor, int $thresholdMinor): self
    {
        return new self(
            "This charge of {$amountMinor} minor units exceeds the {$thresholdMinor} approval threshold and requires an approver.",
            ['amount_minor' => $amountMinor, 'threshold_minor' => $thresholdMinor],
        );
    }

    public function errorCode(): string
    {
        return 'AD_HOC_CHARGE_REQUIRES_APPROVAL';
    }
}
