<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book B FIN-03 §4/BR-FIN-03-010. A credit note above
 * `finance.credit_note_approval_threshold_minor` needs `approvedByUserId`
 * before it may be issued.
 */
class CreditNoteRequiresApprovalException extends DomainException
{
    public static function aboveThreshold(int $amountMinor, int $thresholdMinor): self
    {
        return new self(
            "This credit note of {$amountMinor} minor units exceeds the {$thresholdMinor} approval threshold and requires an approver.",
            ['amount_minor' => $amountMinor, 'threshold_minor' => $thresholdMinor],
        );
    }

    public function errorCode(): string
    {
        return 'CREDIT_NOTE_REQUIRES_APPROVAL';
    }
}
