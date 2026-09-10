<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Security\Models\KeyIssue;

/**
 * ACT-ReturnKey (Book H2 OPS-06 §4/BR-OPS-06-006/007).
 */
final class ReturnKeyAction extends Action
{
    public function execute(int $keyIssueId, int $receivedByUserId): KeyIssue
    {
        $issue = KeyIssue::with('key')->findOrFail($keyIssueId);

        if (! in_array($issue->status, ['issued', 'overdue'], true)) {
            throw new InvalidStateTransitionException(
                "Key issue #{$issue->id} is not currently issued (status {$issue->status}).",
                ['key_issue_id' => $issue->id, 'status' => $issue->status],
            );
        }

        return $this->transaction(function () use ($issue, $receivedByUserId): KeyIssue {
            $issue->update([
                'returned_at' => now(),
                'received_by' => $receivedByUserId,
                'status' => 'returned',
            ]);

            $issue->key->update(['status' => 'available']);

            return $issue;
        });
    }
}
