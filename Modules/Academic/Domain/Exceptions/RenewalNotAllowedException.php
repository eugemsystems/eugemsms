<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-10 §4/BR-ACA-10-003. Renewal is permitted up to
 * `max_renewals` and only while the loan is still active.
 */
class RenewalNotAllowedException extends DomainException
{
    public static function forLoan(int $loanId, string $reason): self
    {
        return new self(
            "Loan #{$loanId} cannot be renewed: {$reason}.",
            ['loan_id' => $loanId, 'reason' => $reason],
        );
    }

    public function errorCode(): string
    {
        return 'LIBRARY_RENEWAL_NOT_ALLOWED';
    }
}
