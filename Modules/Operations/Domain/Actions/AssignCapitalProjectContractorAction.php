<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Operations\Models\CapitalProject;
use Modules\Stores\Models\Supplier;

/**
 * ACT-AssignCapitalProjectContractor (Book H2 OPS-02 §2). Names the main contractor of
 * a project that is still being delivered. Only an approved, active supplier of the
 * school can be named — a pending or blacklisted one cannot.
 */
final class AssignCapitalProjectContractorAction extends Action
{
    public function execute(int $capitalProjectId, int $supplierId): CapitalProject
    {
        return $this->transaction(function () use ($capitalProjectId, $supplierId): CapitalProject {
            $project = CapitalProject::query()->lockForUpdate()->findOrFail($capitalProjectId);
            $supplier = Supplier::query()->findOrFail($supplierId);

            if (! in_array($project->status, ['planning', 'approved', 'in_progress'], true)) {
                throw new InvalidStateTransitionException('The contractor of a completed or cancelled project cannot be changed.');
            }

            if ($supplier->status !== 'active') {
                throw new InvalidStateTransitionException('Only an approved, active supplier can be the main contractor.');
            }

            $project->update(['main_contractor_id' => $supplier->id]);

            return $project;
        });
    }
}
