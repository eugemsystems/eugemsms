<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book E ACA-07 §3 ⭐/AC-ACA-07-001. `release_at` is enforced
 * server-side for absolutely every caller — there is no override
 * parameter anywhere in `ReleaseExaminationPaperAction`, including for
 * a Super Admin.
 */
class PaperReleaseNotYetDueException extends DomainException
{
    public static function forPaper(int $paperId, string $releaseAt): self
    {
        return new self(
            "Paper #{$paperId} does not release until {$releaseAt}.",
            ['paper_id' => $paperId, 'release_at' => $releaseAt],
        );
    }

    public function errorCode(): string
    {
        return 'PAPER_RELEASE_NOT_YET_DUE';
    }
}
