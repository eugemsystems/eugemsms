<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateStaffAppraisalRubricData;
use Modules\People\Models\StaffAppraisalRubric;

/**
 * Mirrors `Modules\Academic\Domain\Actions\CreateObservationRubricAction` — same
 * criterion/descriptor-levels validation, same reasoning for discrete named levels
 * over a weighted-percent scheme (there is no numeric scoring requirement anywhere
 * in `staff_appraisals`' own schema).
 */
final class CreateStaffAppraisalRubricAction extends Action
{
    public function execute(CreateStaffAppraisalRubricData $data): StaffAppraisalRubric
    {
        $name = trim($data->name);
        $seen = [];

        if ($name === '' || mb_strlen($name) > 150 || $data->criteria === []) {
            throw new InvalidArgumentException('A rubric needs a name and at least one criterion.');
        }

        foreach ($data->criteria as $criterion) {
            $title = trim((string) ($criterion['criterion'] ?? ''));
            $levels = $criterion['descriptor_levels'] ?? null;

            if ($title === '' || in_array($title, $seen, true) || ! is_array($levels) || count($levels) < 2 || array_filter($levels, fn ($level): bool => ! is_string($level) || trim($level) === '') !== []) {
                throw new InvalidArgumentException('Each criterion needs a unique name and at least two named levels.');
            }

            $seen[] = $title;
        }

        return $this->transaction(fn (): StaffAppraisalRubric => StaffAppraisalRubric::create([
            'school_id' => $data->schoolId,
            'name' => $name,
            'criteria' => $data->criteria,
        ]));
    }
}
