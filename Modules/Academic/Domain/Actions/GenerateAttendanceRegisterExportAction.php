<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Academic\Domain\DataObjects\GenerateAttendanceRegisterExportData;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSession;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\SchoolClass;
use Modules\People\Models\Student;

/**
 * ACT-GenerateAttendanceRegisterExport (Book D ACA-04 §6). A class register for a date range as a
 * CSV: one row per learner, one column per day a register was taken, a letter per mark (P present,
 * A absent, L late, E excused, blank not marked) and totals. Built only from stored marks, so an
 * unmarked day is blank, never "present" (BR-ACA-04-018). The range is limited to a year so one
 * request cannot ask for an unbounded file. Cells that could be read as a spreadsheet formula are
 * neutralised.
 */
final class GenerateAttendanceRegisterExportAction extends Action
{
    protected bool $transactional = false;

    private const array LETTERS = ['present' => 'P', 'absent' => 'A', 'late' => 'L', 'excused' => 'E'];

    public function execute(GenerateAttendanceRegisterExportData $data): string
    {
        if ($data->to->lessThan($data->from) || $data->from->diffInDays($data->to) > 366) {
            throw ValidationException::withMessages(['to' => 'Choose a range of up to one year, ending after it starts.']);
        }

        $class = SchoolClass::query()->findOrFail($data->classId);

        $sessions = AttendanceSession::query()
            ->where('class_id', $class->id)->where('mode', 'daily')
            ->whereDate('session_date', '>=', $data->from->toDateString())->whereDate('session_date', '<=', $data->to->toDateString())
            ->orderBy('session_date')->get();
        $dates = $sessions->map(fn (AttendanceSession $s): string => $s->session_date->toDateString())->unique()->values();

        $records = AttendanceRecord::query()->whereIn('session_id', $sessions->pluck('id'))->get()->groupBy('student_id');
        $students = Student::query()->whereIn('id', $records->keys())->orderBy('last_name')->orderBy('first_name')->get();

        $rows = [['Admission no', 'Surname', 'First name', ...$dates->all(), 'Present', 'Late', 'Absent', 'Excused']];

        foreach ($students as $student) {
            $byDate = $records->get($student->id)->keyBy(fn (AttendanceRecord $r): string => $r->session_date->toDateString());
            $count = fn (string $status): int => $byDate->where('status', $status)->count();

            $rows[] = [
                $student->admission_number, $student->last_name, $student->first_name,
                ...$dates->map(function (string $date) use ($byDate): string {
                    $mark = $byDate->get($date);

                    return $mark === null ? '' : (self::LETTERS[$mark->status] ?? '');
                })->all(),
                $count('present'), $count('late'), $count('absent'), $count('excused'),
            ];
        }

        return implode("\n", array_map(fn (array $row): string => implode(',', array_map(fn (mixed $cell): string => '"'.str_replace('"', '""', $this->safe((string) $cell)).'"', $row)), $rows))."\n";
    }

    private function safe(string $cell): string
    {
        return $cell !== '' && in_array($cell[0], ['=', '+', '-', '@'], true) && ! is_numeric($cell) ? "'".$cell : $cell;
    }
}
