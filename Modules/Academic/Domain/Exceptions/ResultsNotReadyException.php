<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-09 §4/BR-ACA-09-008. A test's final mark is not
 * release-visible to the candidate until every response — auto and
 * manual — is marked and the test is explicitly published.
 */
class ResultsNotReadyException extends DomainException
{
    public static function forTest(int $testId, int $pendingCount): self
    {
        return new self(
            "CBT test #{$testId} still has {$pendingCount} attempt(s) awaiting manual marking.",
            ['test_id' => $testId, 'pending_count' => $pendingCount],
        );
    }

    public function errorCode(): string
    {
        return 'CBT_RESULTS_NOT_READY';
    }
}
