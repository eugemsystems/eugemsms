<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateEstablishmentPostData;
use Modules\People\Models\EstablishmentPost;

/**
 * ACT-CreateEstablishmentPost (Book C PPL-04 §2).
 */
final class CreateEstablishmentPostAction extends Action
{
    public function execute(CreateEstablishmentPostData $data): EstablishmentPost
    {
        return $this->transaction(fn (): EstablishmentPost => EstablishmentPost::create([
            'school_id' => $data->schoolId,
            'department_id' => $data->departmentId,
            'title' => $data->title,
            'grade' => $data->grade,
            'approved_count' => $data->approvedCount,
            'filled_count' => 0,
            'is_teaching' => $data->isTeaching,
            'is_active' => true,
        ]));
    }
}
