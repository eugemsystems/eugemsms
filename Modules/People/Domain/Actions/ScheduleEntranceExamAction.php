<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\ScheduleEntranceExamData;
use Modules\People\Models\EntranceExam;
use Modules\People\Models\Intake;

/**
 * ACT-ScheduleEntranceExam (Book C PPL-02 §2). An entrance exam for an intake. Its
 * papers each carry a maximum mark and a weight; the weights must total 100 so a
 * candidate's percentage means one thing.
 */
final class ScheduleEntranceExamAction extends Action
{
    public function execute(ScheduleEntranceExamData $data): EntranceExam
    {
        if (! Intake::query()->whereKey($data->intakeId)->exists()) {
            throw new InvalidArgumentException('That intake does not belong to this school.');
        }

        if (trim($data->name) === '' || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $data->startTime)) {
            throw new InvalidArgumentException('An exam needs a name and a start time (HH:MM).');
        }

        if ($data->papers === []) {
            throw new InvalidArgumentException('An exam needs at least one paper.');
        }

        $weights = 0.0;

        foreach ($data->papers as $paper) {
            if (trim((string) ($paper['subject'] ?? '')) === '' || (float) ($paper['max_mark'] ?? 0) <= 0 || (float) ($paper['weight'] ?? 0) <= 0) {
                throw new InvalidArgumentException('Every paper needs a subject, a maximum mark and a weight above zero.');
            }

            $weights += (float) $paper['weight'];
        }

        if (abs($weights - 100.0) > 0.01) {
            throw new InvalidArgumentException('Paper weights must total 100 (they total '.rtrim(rtrim(number_format($weights, 2), '0'), '.').').');
        }

        if ($data->capacity !== null && $data->capacity < 1) {
            throw new InvalidArgumentException('Capacity must be at least 1.');
        }

        if ($data->passMarkPercent !== null && ($data->passMarkPercent < 0 || $data->passMarkPercent > 100)) {
            throw new InvalidArgumentException('The pass mark must be between 0 and 100.');
        }

        return $this->transaction(fn (): EntranceExam => EntranceExam::create([
            'school_id' => $data->schoolId,
            'intake_id' => $data->intakeId,
            'name' => trim($data->name),
            'exam_date' => $data->examDate->toDateString(),
            'start_time' => $data->startTime,
            'venue' => $data->venue,
            'capacity' => $data->capacity,
            'papers' => array_values($data->papers),
            'pass_mark_percent' => $data->passMarkPercent,
            'status' => 'scheduled',
        ]));
    }
}
