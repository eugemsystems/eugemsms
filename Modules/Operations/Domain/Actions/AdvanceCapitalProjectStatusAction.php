<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Operations\Models\CapitalProject;

/**
 * ACT-AdvanceCapitalProjectStatus (Book H2 OPS-02 §2/BR-OPS-02-015).
 * Admin-UI-pass gap-fill, the same forward-only shape
 * `Modules\Academic\Domain\Actions\AdvanceExaminationSessionStatusAction`
 * already established: `CreateCapitalProjectAction` leaves a project
 * `planning`, but `CompleteCapitalProjectAction` only accepts
 * `approved`/`in_progress` — nothing in the shipped domain layer ever
 * moved it forward. One step at a time, never skipping a stage.
 */
final class AdvanceCapitalProjectStatusAction extends Action
{
    private const array ORDER = ['planning', 'approved', 'in_progress'];

    public function execute(int $capitalProjectId): CapitalProject
    {
        $project = CapitalProject::findOrFail($capitalProjectId);
        $currentIndex = array_search($project->status, self::ORDER, true);

        if ($currentIndex === false || $currentIndex >= count(self::ORDER) - 1) {
            throw new InvalidStateTransitionException(
                "Capital project #{$project->id} cannot advance from its current status ({$project->status}).",
                ['capital_project_id' => $project->id, 'status' => $project->status],
            );
        }

        $next = self::ORDER[$currentIndex + 1];

        return $this->transaction(fn (): CapitalProject => tap($project)->update(['status' => $next]));
    }
}
