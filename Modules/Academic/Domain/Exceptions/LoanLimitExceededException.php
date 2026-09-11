<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-10 §4/BR-ACA-10-002/AC-ACA-10-003. A borrower at their
 * concurrent-loan limit cannot check out another item until one is
 * returned or the limit is raised for that case with a reason — this
 * module doesn't yet build the override-with-reason path, only the
 * refusal.
 */
class LoanLimitExceededException extends DomainException
{
    public static function forBorrower(string $borrowerType, int $borrowerId, int $limit): self
    {
        return new self(
            "Borrower [{$borrowerType} #{$borrowerId}] is already at their concurrent loan limit of {$limit}.",
            ['borrower_type' => $borrowerType, 'borrower_id' => $borrowerId, 'limit' => $limit],
        );
    }

    public function errorCode(): string
    {
        return 'LIBRARY_LOAN_LIMIT_EXCEEDED';
    }
}
