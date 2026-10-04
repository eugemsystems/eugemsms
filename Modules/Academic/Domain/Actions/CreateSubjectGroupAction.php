<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateSubjectGroupData;
use Modules\Academic\Models\SubjectGroup;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateSubjectGroup (Book D ACA-01 §2/§5 ⭐/BR-ACA-01-004). New in
 * this admin-UI pass — no Action created a `SubjectGroup` before now.
 * This is the join point `FIN-02` prices per-subject rates against
 * (`fee_structure_items.subject_rate_map`, keyed by `code`): renaming a
 * group's code here without realising that is exactly the mistake the
 * spec's own screen note warns about, which is why `Curriculum\Groups`
 * surfaces the mapped rate inline rather than leaving it to be
 * discovered the hard way.
 */
final class CreateSubjectGroupAction extends Action
{
    public function execute(CreateSubjectGroupData $data): SubjectGroup
    {
        return $this->transaction(fn (): SubjectGroup => SubjectGroup::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'description' => $data->description,
            'requires_laboratory' => $data->requiresLaboratory,
            'requires_workshop' => $data->requiresWorkshop,
            'sort_order' => $data->sortOrder,
            'is_active' => true,
        ]));
    }
}
