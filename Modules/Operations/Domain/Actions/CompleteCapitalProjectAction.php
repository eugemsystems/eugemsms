<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Operations\Models\CapitalProject;
use Modules\Stores\Domain\Actions\CapitalizeAssetAction;
use Modules\Stores\Domain\DataObjects\CapitalizeAssetData;
use Modules\Stores\Models\BudgetLine;

/**
 * ACT-CompleteCapitalProject (Book H2 OPS-02 §6/BR-OPS-02-015). A
 * project with `capitalise_on_completion` set and both a budget line
 * and an asset category recorded is capitalised for real through
 * `FIN-10`'s own `CapitalizeAssetAction` — `costCentreId` and the
 * contra account both come from the project's own `BudgetLine`
 * (`cost_centre_id`/`account_id`), the same account its committed and
 * actual spend has been posting to all along. A project missing
 * either is completed without capitalisation — nothing to derive an
 * asset entry from.
 */
final class CompleteCapitalProjectAction extends Action
{
    public function __construct(
        private readonly CapitalizeAssetAction $capitalizeAsset,
    ) {}

    public function execute(int $capitalProjectId, int $academicYearId, int $termId, int $completedByUserId, ?Carbon $actualCompletion = null): CapitalProject
    {
        $project = CapitalProject::findOrFail($capitalProjectId);

        if (! in_array($project->status, ['approved', 'in_progress'], true)) {
            throw new InvalidStateTransitionException(
                "Capital project #{$project->id} must be approved or in progress to complete (currently {$project->status}).",
                ['capital_project_id' => $project->id, 'status' => $project->status],
            );
        }

        $completionDate = $actualCompletion ?? Carbon::now();

        return $this->transaction(function () use ($project, $completionDate, $academicYearId, $termId, $completedByUserId): CapitalProject {
            $project->update([
                'status' => 'completed',
                'actual_completion' => $completionDate->toDateString(),
            ]);

            if ($project->capitalise_on_completion && $project->asset_category_id !== null && $project->budget_line_id !== null) {
                $budgetLine = BudgetLine::findOrFail($project->budget_line_id);

                $this->capitalizeAsset->execute(new CapitalizeAssetData(
                    schoolId: $project->school_id,
                    academicYearId: $academicYearId,
                    termId: $termId,
                    categoryId: $project->asset_category_id,
                    name: $project->name,
                    acquisitionDate: $completionDate,
                    acquisitionCostMinor: $project->spent_minor,
                    currency: $project->currency,
                    acquisitionSource: 'capital_project',
                    costCentreId: $budgetLine->cost_centre_id,
                    contraAccountId: $budgetLine->account_id,
                    performedByUserId: $completedByUserId,
                ));
            }

            return $project;
        });
    }
}
