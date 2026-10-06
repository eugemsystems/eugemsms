<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Attendance;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\GenerateAttendanceRegisterExportAction;
use Modules\Academic\Domain\DataObjects\GenerateAttendanceRegisterExportData;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSession;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\People\Models\Student;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `Attendance\Reports` (Book D ACA-04 §5, `academic.attendance.view`). Four reads over the stored
 * marks, none of which treats an unmarked day as present: a learner's calendar heatmap, a class
 * report for a date range, the absence follow-up list (recent absences and whether the parent has
 * been told) and a CSV export of a class register.
 */
#[Title('Attendance reports')]
#[Layout('layouts.app')]
final class Reports extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;

    public string $tab = 'class';

    public string $from = '';

    public string $to = '';

    public ?int $classId = null;

    public string $learnerSearch = '';

    public ?int $studentId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.attendance.view');

        $term = SessionContext::term();
        $this->from = ($term !== null ? $term->starts_on : now()->startOfMonth())->toDateString();
        $this->to = min(now(), $term !== null ? $term->ends_on : now())->toDateString();
    }

    public function export(): ?StreamedResponse
    {
        $this->authorizePermission('academic.attendance.view');
        $this->resetErrorBag();

        if ($this->classId === null) {
            $this->addError('classId', __('Choose a class.'));

            return null;
        }

        try {
            $csv = app(GenerateAttendanceRegisterExportAction::class)->execute(new GenerateAttendanceRegisterExportData(
                SchoolClass::query()->where('school_id', $this->school->id)->findOrFail($this->classId)->id, Carbon::parse($this->from), Carbon::parse($this->to),
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return null;
        }

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, "attendance-register-{$this->from}-{$this->to}.csv", ['Content-Type' => 'text/csv']);
    }

    public function render(): View
    {
        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();

        return view('academic::attendance.reports', [
            'classes' => SchoolClass::query()->where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'classReport' => $this->tab === 'class' && $this->classId !== null ? $this->classReport($from, $to) : [],
            'candidates' => $this->tab === 'heatmap' && trim($this->learnerSearch) !== '' ? Student::query()->where('school_id', $this->school->id)
                ->where(fn ($q) => $q->where('last_name', 'like', '%'.trim($this->learnerSearch).'%')->orWhere('admission_number', 'like', '%'.trim($this->learnerSearch).'%'))
                ->orderBy('last_name')->limit(8)->get(['id', 'first_name', 'last_name', 'admission_number']) : collect(),
            'heatmap' => $this->tab === 'heatmap' && $this->studentId !== null ? $this->heatmap($from, $to) : null,
            'followUp' => $this->tab === 'followup' ? $this->followUp() : collect(),
        ]);
    }

    /**
     * @return list<array{student: Student, present: int, late: int, absent: int, excused: int, percent: float|null}>
     */
    private function classReport(Carbon $from, Carbon $to): array
    {
        $sessionIds = AttendanceSession::query()->where('class_id', $this->classId)->where('mode', 'daily')
            ->whereDate('session_date', '>=', $from->toDateString())->whereDate('session_date', '<=', $to->toDateString())->pluck('id');
        $records = AttendanceRecord::query()->whereIn('session_id', $sessionIds)->get()->groupBy('student_id');
        $students = Student::query()->whereIn('id', $records->keys())->orderBy('last_name')->get()->keyBy('id');
        $rows = [];

        foreach ($records as $studentId => $marks) {
            $student = $students->get($studentId);

            if ($student === null) {
                continue;
            }

            $counts = ['present' => $marks->where('status', 'present')->count(), 'late' => $marks->where('status', 'late')->count(), 'absent' => $marks->where('status', 'absent')->count(), 'excused' => $marks->where('status', 'excused')->count()];
            $counted = $counts['present'] + $counts['late'] + $counts['absent'];

            $rows[] = ['student' => $student] + $counts + ['percent' => $counted > 0 ? round(($counts['present'] + $counts['late']) / $counted * 100, 1) : null];
        }

        usort($rows, fn (array $a, array $b): int => ($a['percent'] ?? 101) <=> ($b['percent'] ?? 101));

        return $rows;
    }

    /**
     * Weeks as rows, Monday to Friday as columns; a day with no mark stays blank.
     *
     * @return array{student: Student, weeks: list<list<array{date: string, status: string|null}|null>>}|null
     */
    private function heatmap(Carbon $from, Carbon $to): ?array
    {
        $student = Student::query()->where('school_id', $this->school->id)->find($this->studentId);

        if ($student === null) {
            return null;
        }

        $marks = AttendanceRecord::query()->where('student_id', $student->id)->whereDate('session_date', '>=', $from->toDateString())->whereDate('session_date', '<=', $to->toDateString())
            ->get()->keyBy(fn (AttendanceRecord $r): string => $r->session_date->toDateString());
        $weeks = [];

        for ($weekStart = $from->copy()->startOfWeek(); $weekStart->lessThanOrEqualTo($to); $weekStart->addWeek()) {
            $week = [];

            for ($day = 0; $day < 5; $day++) {
                $date = $weekStart->copy()->addDays($day);
                $week[] = $date->between($from, $to) ? ['date' => $date->toDateString(), 'status' => $marks->get($date->toDateString())?->status] : null;
            }

            $weeks[] = $week;
        }

        return ['student' => $student, 'weeks' => $weeks];
    }

    /**
     * @return Collection<int, AttendanceRecord>
     */
    private function followUp(): Collection
    {
        return AttendanceRecord::query()->with('student:id,first_name,last_name,admission_number')
            ->where('status', 'absent')->whereDate('session_date', '>=', now()->subDays(14)->toDateString())
            ->orderByDesc('session_date')->limit(100)->get();
    }
}
