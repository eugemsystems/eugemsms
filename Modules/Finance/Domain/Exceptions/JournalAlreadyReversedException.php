<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-01-015. A journal already reversed cannot be reversed again.
 */
class JournalAlreadyReversedException extends DomainException
{
    public static function forJournal(int $journalId): self
    {
        return new self("Journal [{$journalId}] has already been reversed.", ['journal_id' => $journalId]);
    }

    public function errorCode(): string
    {
        return 'JOURNAL_ALREADY_REVERSED';
    }
}
