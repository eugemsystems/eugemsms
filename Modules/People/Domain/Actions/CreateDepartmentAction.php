<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateDepartmentData;
use Modules\People\Models\Department;

/**
 * ACT-CreateDepartment (Book C PPL-04 §2).
 */
final class CreateDepartmentAction extends Action
{
    public function execute(CreateDepartmentData $data): Department
    {
        return $this->transaction(fn (): Department => Department::create([
            'school_id' => $data->schoolId,
            'parent_id' => $data->parentId,
            'code' => $data->code,
            'name' => $data->name,
            'type' => $data->type,
            'cost_centre_id' => $data->costCentreId,
            'is_active' => true,
        ]));
    }
}
