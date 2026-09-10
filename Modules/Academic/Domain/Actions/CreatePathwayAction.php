<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreatePathwayData;
use Modules\Academic\Models\Pathway;
use Modules\Core\Domain\Actions\Action;

final class CreatePathwayAction extends Action
{
    public function execute(CreatePathwayData $data): Pathway
    {
        return $this->transaction(fn (): Pathway => Pathway::create([
            'school_id' => $data->schoolId,
            'framework_id' => $data->frameworkId,
            'code' => $data->code,
            'name' => $data->name,
            'description' => $data->description,
            'applies_from_level_ordinal' => $data->appliesFromLevelOrdinal,
            'is_default' => $data->isDefault,
            'is_active' => true,
        ]));
    }
}
