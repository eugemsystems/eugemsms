<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Operations\Models\CapitalProjectMilestone;

/**
 * ACT-CompleteCapitalProjectMilestone (Book H2 OPS-02 §2). Marks a milestone done on
 * the date it was reached. Only a project that is under way can have milestones
 * completed, a milestone is completed once, and the date cannot be in the future.
 */
final class CompleteCapitalProjectMilestoneAction extends Action
{
    public function execute(int $milestoneId, CarbonInterface $completedOn): CapitalProjectMilestone
    {
        return $this->transaction(function () use ($milestoneId, $completedOn): CapitalProjectMilestone {
            $milestone = CapitalProjectMilestone::query()->lockForUpdate()->with('project')->findOrFail($milestoneId);

            if ($milestone->status === 'completed') {
                throw new InvalidStateTransitionException('This milestone is already completed.');
            }

            if ($milestone->project->status !== 'in_progress') {
                throw new InvalidStateTransitionException('Milestones are completed while the project is in progress.');
            }

            if ($completedOn->isFuture()) {
                throw new InvalidStateTransitionException('A milestone cannot be completed on a future date.');
            }

            $milestone->update(['status' => 'completed', 'completed_date' => $completedOn->toDateString()]);

            return $milestone;
        });
    }
}
