<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\Subject;
use Modules\Compliance\Domain\DataObjects\ZimsecPassRateResult;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Compliance\Models\ZimsecResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * ACT-AnalyseZimsecPassRates (Book H3 CMP-01 §3/BR-CMP-01-012). Pass
 * grades are versioned config (`compliance.zimsec_pass_grades_{level}`),
 * never hard-coded — ZIMSEC's own grading scale differs by level and
 * has changed over time. Breaks down by subject and by class (joined
 * through `Subject.zimsec_subject_code` back to ACA-02's
 * `learner_subject_enrolments` for the class a candidate sat the
 * subject in); historical comparison walks every earlier
 * `zimsec_registrations` row for the same school and exam level.
 *
 * **Scope boundary**: "by teacher" (BR-CMP-01-012's third axis) is
 * deliberately not built — `learner_subject_enrolments` carries
 * `class_id` but no `teacher_id`, and no clean subject-class-teacher
 * link surfaced without a deeper pass through `Modules\Academic`'s own
 * timetabling (ACA-03), out of proportion to this module.
 */
final class AnalyseZimsecPassRatesAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $registrationId): ZimsecPassRateResult
    {
        $registration = ZimsecRegistration::findOrFail($registrationId);
        $passGrades = $this->passGrades($registration);

        $results = ZimsecResult::where('registration_id', $registration->id)->get();

        return new ZimsecPassRateResult(
            bySubject: $this->bySubject($results, $passGrades),
            byClass: $this->byClass($registration, $results, $passGrades),
            historical: $this->historical($registration, $passGrades),
        );
    }

    /**
     * @return array<int, string>
     */
    private function passGrades(ZimsecRegistration $registration): array
    {
        $key = "compliance.zimsec_pass_grades_{$registration->exam_level}";
        $raw = (string) $this->settings->get($key, new ScopeChain(schoolId: $registration->school_id));

        return array_map('trim', explode(',', $raw));
    }

    /**
     * @param  Collection<int, ZimsecResult>  $results
     * @param  array<int, string>  $passGrades
     * @return array<int, array{subject_code: string, subject_name: string, candidates: int, passes: int, pass_rate: float}>
     */
    private function bySubject(Collection $results, array $passGrades): array
    {
        return $results->groupBy('subject_code')->map(function (Collection $rows, string $code) use ($passGrades): array {
            $candidates = $rows->count();
            $passes = $rows->filter(fn (ZimsecResult $row): bool => in_array($row->grade, $passGrades, true))->count();

            return [
                'subject_code' => $code,
                'subject_name' => $rows->first()->subject_name,
                'candidates' => $candidates,
                'passes' => $passes,
                'pass_rate' => $candidates > 0 ? round($passes / $candidates * 100, 2) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, ZimsecResult>  $results
     * @param  array<int, string>  $passGrades
     * @return array<int, array{class_id: int|null, candidates: int, passes: int, pass_rate: float}>
     */
    private function byClass(ZimsecRegistration $registration, Collection $results, array $passGrades): array
    {
        $subjectsByZimsecCode = Subject::withoutGlobalScopes()
            ->where('school_id', $registration->school_id)
            ->whereNotNull('zimsec_subject_code')
            ->get()
            ->keyBy('zimsec_subject_code');

        $classByResult = $results->map(function (ZimsecResult $result) use ($subjectsByZimsecCode): ?int {
            $subject = $subjectsByZimsecCode->get($result->subject_code);

            if ($subject === null) {
                return null;
            }

            return LearnerSubjectEnrolment::withoutGlobalScopes()
                ->where('student_id', $result->student_id)
                ->where('subject_id', $subject->id)
                ->value('class_id');
        });

        return $results->values()
            ->groupBy(fn (ZimsecResult $result, int $index) => $classByResult[$index] ?? 'unassigned')
            ->map(function (Collection $rows, string $classKey) use ($passGrades): array {
                $candidates = $rows->count();
                $passes = $rows->filter(fn (ZimsecResult $row): bool => in_array($row->grade, $passGrades, true))->count();

                return [
                    'class_id' => $classKey === 'unassigned' ? null : (int) $classKey,
                    'candidates' => $candidates,
                    'passes' => $passes,
                    'pass_rate' => $candidates > 0 ? round($passes / $candidates * 100, 2) : 0.0,
                ];
            })->values()->all();
    }

    /**
     * @param  array<int, string>  $passGrades
     * @return array<int, array{exam_series: string, candidates: int, passes: int, pass_rate: float}>
     */
    private function historical(ZimsecRegistration $registration, array $passGrades): array
    {
        $priorRegistrations = ZimsecRegistration::where('school_id', $registration->school_id)
            ->where('exam_level', $registration->exam_level)
            ->where('id', '!=', $registration->id)
            ->orderBy('registration_closes_on')
            ->get();

        return $priorRegistrations->map(function (ZimsecRegistration $prior) use ($passGrades): array {
            $rows = ZimsecResult::where('registration_id', $prior->id)->get();
            $candidates = $rows->count();
            $passes = $rows->filter(fn (ZimsecResult $row): bool => in_array($row->grade, $passGrades, true))->count();

            return [
                'exam_series' => $prior->exam_series,
                'candidates' => $candidates,
                'passes' => $passes,
                'pass_rate' => $candidates > 0 ? round($passes / $candidates * 100, 2) : 0.0,
            ];
        })->values()->all();
    }
}
