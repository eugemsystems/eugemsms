<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Models\Assignment;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-CloseAssignment (Book K ACA-08 §2, `status` published → closed). A
 * closed assignment takes no more submissions; marking continues. Only a
 * published assignment can be closed.
 */
final class CloseAssignmentAction extends Action
{
    public function execute(int $assignmentId): Assignment
    {
        $assignment = Assignment::findOrFail($assignmentId);

        if ($assignment->status !== 'published') {
            throw new InvalidStateTransitionException("An assignment in [{$assignment->status}] cannot be closed.", ['assignment_id' => $assignment->id, 'status' => $assignment->status]);
        }

        return $this->transaction(function () use ($assignment): Assignment {
            $assignment->update(['status' => 'closed']);

            return $assignment->fresh();
        });
    }
}
