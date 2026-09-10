<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Models\ZimsecCandidate;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ConfirmStatementOfEntry (Book H3 CMP-01 §3/BR-CMP-01-009). A
 * guardian or learner confirming receipt of their statement of entry
 * — tracked separately from distribution itself.
 */
final class ConfirmStatementOfEntryAction extends Action
{
    public function execute(int $candidateId): ZimsecCandidate
    {
        return $this->transaction(function () use ($candidateId): ZimsecCandidate {
            $candidate = ZimsecCandidate::findOrFail($candidateId);

            if ($candidate->statement_of_entry_id === null) {
                throw new InvalidStateTransitionException(
                    "ZIMSEC candidate #{$candidate->id} has no statement of entry to confirm.",
                    ['candidate_id' => $candidate->id],
                );
            }

            $candidate->update(['statement_confirmed' => true]);

            return $candidate;
        });
    }
}
