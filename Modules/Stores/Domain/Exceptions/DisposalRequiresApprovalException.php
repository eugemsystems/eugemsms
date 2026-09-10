<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H1 FIN-10 §6/BR-FIN-10-013. A disposal whose net book value is
 * at or above `assets.disposal_approval_threshold_minor` needs
 * `approvedByUserId` before it may post — the default threshold is 0,
 * so every disposal requires sign-off unless a school deliberately
 * raises it.
 */
class DisposalRequiresApprovalException extends DomainException
{
    public static function aboveThreshold(int $netBookValueMinor, int $thresholdMinor): self
    {
        return new self(
            "This disposal's net book value of {$netBookValueMinor} minor units meets or exceeds the {$thresholdMinor} approval threshold and requires an approver.",
            ['net_book_value_minor' => $netBookValueMinor, 'threshold_minor' => $thresholdMinor],
        );
    }

    public function errorCode(): string
    {
        return 'DISPOSAL_REQUIRES_APPROVAL';
    }
}
