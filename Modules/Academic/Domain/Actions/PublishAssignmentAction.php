<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\PublishAssignmentData;
use Modules\Academic\Models\Assignment;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

final class PublishAssignmentAction extends Action
{
    public function execute(PublishAssignmentData $data): Assignment
    {
        $assignment = Assignment::findOrFail($data->assignmentId);

        if ($assignment->status !== 'draft') {
            throw new InvalidStateTransitionException(
                "An assignment in [{$assignment->status}] cannot be published.",
                ['assignment_id' => $assignment->id, 'status' => $assignment->status],
            );
        }

        return $this->transaction(function () use ($assignment): Assignment {
            $assignment->update(['status' => 'published']);

            return $assignment->fresh();
        });
    }
}
