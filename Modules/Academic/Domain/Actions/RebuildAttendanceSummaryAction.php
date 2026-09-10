<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\RebuildAttendanceSummaryData;
use Modules\Academic\Domain\Events\ChronicAbsenteeIdentified;
use Modules\Academic\Domain\Events\ConsecutiveAbsenceThresholdReached;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSummary;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Student;

/**
 * ACT-RebuildAttendanceSummary (Book D ACA-04 §2/§4/BR-ACA-04-011/012/013).
 * Recomputes strictly from `attendance_records` — this cache is never
 * itself trusted, only ever overwritten from source, same discipline
 * as every balance cache in `FIN-01`. A `counts_as_present` reason
 * (BR-ACA-04-005) counts toward the present side of the percentage;
 * "unexplained" for the consecutive-run and chronic checks means an
 * absence whose reason code (if any) does not carry
 * `counts_as_present`.
 */
final class RebuildAttendanceSummaryAction extends Action
{
    public function __construct(private readonly SettingResolver $settings) {}

    public function execute(RebuildAttendanceSummaryData $data): AttendanceSummary
    {
        $student = Student::findOrFail($data->studentId);

        $records = AttendanceRecord::query()
            ->where('student_id', $data->studentId)
            ->where('term_id', $data->termId)
            ->when($data->subjectId !== null, fn ($q) => $q->whereHas('session', fn ($s) => $s->where('subject_id', $data->subjectId)))
            ->with('reasonCode')
            ->orderBy('session_date')
            ->get();

        $expected = $records->count();
        $present = $records->where('status', 'present')->count();
        $late = $records->where('status', 'late')->count();

        $absentRecords = $records->where('status', 'absent');
        $presentEquivalentAbsences = $absentRecords->filter(fn (AttendanceRecord $r): bool => $r->reasonCode?->counts_as_present === true)->count();
        $absentAuthorised = $absentRecords->filter(fn (AttendanceRecord $r): bool => $r->reasonCode?->is_authorised === true && $r->reasonCode->counts_as_present !== true)->count();
        $absentUnauthorised = $absentRecords->count() - $presentEquivalentAbsences - $absentAuthorised;

        $presentEquivalent = $present + $late + $presentEquivalentAbsences;
        $percent = $expected > 0 ? round(($presentEquivalent / $expected) * 100, 2) : null;

        $consecutiveMax = $this->consecutiveUnexplainedRun($records);

        $chronicThreshold = (int) $this->settings->get('attendance.chronic_percent_threshold', new ScopeChain(schoolId: $student->school_id));
        $isChronic = $percent !== null && $percent < $chronicThreshold;

        $summary = AttendanceSummary::updateOrCreate(
            [
                'school_id' => $student->school_id,
                'student_id' => $data->studentId,
                'term_id' => $data->termId,
                'scope' => $data->scope,
                'subject_id' => $data->subjectId,
            ],
            [
                'sessions_expected' => $expected,
                'present_count' => $present,
                'absent_authorised' => $absentAuthorised,
                'absent_unauthorised' => $absentUnauthorised,
                'late_count' => $late,
                'attendance_percent' => $percent,
                'consecutive_absent_max' => $consecutiveMax,
                'is_chronic_absentee' => $isChronic,
                'rebuilt_at' => Carbon::now(),
            ],
        );

        $chronicDays = (int) $this->settings->get('attendance.chronic_threshold_days', new ScopeChain(schoolId: $student->school_id));

        if ($consecutiveMax >= $chronicDays && $chronicDays > 0) {
            event(new ConsecutiveAbsenceThresholdReached($summary, $consecutiveMax));
        }

        if ($isChronic) {
            event(new ChronicAbsenteeIdentified($summary));
        }

        return $summary;
    }

    /**
     * @param  Collection<int, AttendanceRecord>  $records
     */
    private function consecutiveUnexplainedRun($records): int
    {
        $unexplainedDates = $records
            ->filter(fn (AttendanceRecord $r): bool => $r->status === 'absent' && $r->reasonCode?->counts_as_present !== true)
            ->pluck('session_date')
            ->map(fn (CarbonInterface $date): string => $date->toDateString())
            ->unique()
            ->sort()
            ->values();

        $max = 0;
        $current = 0;
        $previous = null;

        foreach ($unexplainedDates as $dateString) {
            $date = Carbon::parse($dateString);

            $current = ($previous !== null && $previous->copy()->addDay()->isSameDay($date)) ? $current + 1 : 1;
            $max = max($max, $current);
            $previous = $date;
        }

        return $max;
    }
}
