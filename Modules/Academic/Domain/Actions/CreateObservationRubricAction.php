<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateObservationRubricData;
use Modules\Academic\Models\ObservationRubric;
use Modules\Core\Domain\Actions\Action;

final class CreateObservationRubricAction extends Action
{
    public function execute(CreateObservationRubricData $data): ObservationRubric
    {
        return $this->transaction(fn (): ObservationRubric => ObservationRubric::create([
            'school_id' => $data->schoolId,
            'name' => $data->name,
            'criteria' => $data->criteria,
        ]));
    }
}
