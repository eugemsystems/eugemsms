<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Operations\Domain\DataObjects\CreateCapitalProjectData;
use Modules\Operations\Models\CapitalProject;

/**
 * ACT-CreateCapitalProject (Book H2 OPS-02 §6/BR-OPS-02-015).
 */
final class CreateCapitalProjectAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
    ) {}

    public function execute(CreateCapitalProjectData $data): CapitalProject
    {
        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'capital_project',
            allocatedByUserId: $data->createdByUserId,
        ));

        return $this->transaction(fn (): CapitalProject => CapitalProject::create([
            'school_id' => $data->schoolId,
            'project_number' => $number->formatted_number,
            'name' => $data->name,
            'description' => $data->description,
            'budget_minor' => $data->budgetMinor,
            'currency' => $data->currency,
            'committed_minor' => 0,
            'spent_minor' => 0,
            'budget_line_id' => $data->budgetLineId,
            'asset_category_id' => $data->assetCategoryId,
            'starts_on' => $data->startsOn->toDateString(),
            'target_completion' => $data->targetCompletion?->toDateString(),
            'project_manager_id' => $data->projectManagerId,
            'main_contractor_id' => $data->mainContractorId,
            'status' => 'planning',
            'capitalise_on_completion' => $data->capitaliseOnCompletion,
        ]));
    }
}
