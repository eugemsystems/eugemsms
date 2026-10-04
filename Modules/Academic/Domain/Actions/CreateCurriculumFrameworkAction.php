<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateCurriculumFrameworkData;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateCurriculumFramework (Book D ACA-01 §2/§5, `curriculum.manage`).
 * The admin-UI pass found no domain Action for this at all — every
 * `CurriculumFramework` row in this codebase before this pass was a
 * test/demo fixture created via `CurriculumFramework::factory()`
 * (`AcademicServiceProvider::registerTenantModels()`), never through a
 * reviewable write path. New framework starts `draft`; a school
 * activates it explicitly once ready (BR-ACA-01-001/002 — multiple
 * frameworks may coexist, each versioned by its own `effective_from`).
 */
final class CreateCurriculumFrameworkAction extends Action
{
    public function execute(CreateCurriculumFrameworkData $data): CurriculumFramework
    {
        return $this->transaction(fn (): CurriculumFramework => CurriculumFramework::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'authority' => $data->authority,
            'effective_from' => $data->effectiveFrom->toDateString(),
            'effective_to' => null,
            'status' => 'draft',
            'continuous_assessment_model' => $data->continuousAssessmentModel,
            'reference_circular' => $data->referenceCircular,
            'notes' => $data->notes,
            'created_by' => $data->createdByUserId,
        ]));
    }
}
