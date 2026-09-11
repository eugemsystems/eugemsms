<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-09 §2/§4. A test can only be started while its own
 * status permits it and the current time falls within
 * `opens_at`..`closes_at`.
 */
class TestNotOpenException extends DomainException
{
    public static function forTest(int $testId): self
    {
        return new self(
            "CBT test #{$testId} is not currently open for attempts.",
            ['test_id' => $testId],
        );
    }

    public function errorCode(): string
    {
        return 'CBT_TEST_NOT_OPEN';
    }
}
