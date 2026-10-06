<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\CreateObservationRubricData;
use Modules\Academic\Models\ObservationRubric;
use Modules\Core\Domain\Actions\Action;

final class CreateObservationRubricAction extends Action
{
    public function execute(CreateObservationRubricData $data): ObservationRubric
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

        return $this->transaction(fn (): ObservationRubric => ObservationRubric::create([
            'school_id' => $data->schoolId,
            'name' => $name,
            'criteria' => $data->criteria,
        ]));
    }
}
