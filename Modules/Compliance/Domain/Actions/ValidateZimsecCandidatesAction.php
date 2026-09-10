<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\Support\SubjectSelectionRuleEngine;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\Subject;
use Modules\Compliance\Models\ZimsecCandidate;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Compliance\Models\ZimsecValidationRule;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Student;

/**
 * ACT-ValidateZimsecCandidates (Book H3 CMP-01 §3 ⭐/BR-CMP-01-002/003/
 * 005 (AC-CMP-01-001/004)). Two independent rule sources, deliberately
 * not merged: this module's own `zimsec_validation_rules` — data, not
 * code, because ZIMSEC's field requirements change between series —
 * covers bio-data (surname, DOB, national ID...); subject-count and
 * pathway validation is NOT duplicated here and instead reuses ACA-01's
 * real `SubjectSelectionRuleEngine` against the source `ExaminationCandidate`'s
 * `entered_subjects` (BR-CMP-01-005) — the ACA-01 maximum lives in one
 * place only. `error` severity blocks export; `warning` only flags.
 */
final class ValidateZimsecCandidatesAction extends Action
{
    public function __construct(
        private readonly SubjectSelectionRuleEngine $ruleEngine,
    ) {}

    /**
     * @return Collection<int, ZimsecCandidate>
     */
    public function execute(int $registrationId): Collection
    {
        $registration = ZimsecRegistration::findOrFail($registrationId);
        $rules = $this->activeRules($registration->school_id, $registration->exam_level);

        return $this->transaction(function () use ($registration, $rules): Collection {
            $candidates = ZimsecCandidate::where('registration_id', $registration->id)->get();

            foreach ($candidates as $candidate) {
                $errors = $this->fieldErrors($candidate, $rules);
                $errors = [...$errors, ...$this->subjectCountErrors($registration, $candidate)];

                $status = match (true) {
                    collect($errors)->contains('severity', 'error') => 'errors',
                    $errors !== [] => 'warnings',
                    default => 'valid',
                };

                $candidate->update([
                    'validation_status' => $status,
                    'validation_errors' => $errors === [] ? null : $errors,
                    'status' => $status === 'errors' ? 'draft' : 'validated',
                ]);
            }

            $registration->update([
                'validated_count' => $candidates->where('validation_status', '!=', 'errors')->count(),
                'error_count' => ZimsecCandidate::where('registration_id', $registration->id)->where('validation_status', 'errors')->count(),
            ]);

            return $candidates->fresh();
        });
    }

    /**
     * @return Collection<int, ZimsecValidationRule>
     */
    private function activeRules(int $schoolId, string $examLevel): Collection
    {
        return ZimsecValidationRule::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $schoolId))
            ->where(fn ($q) => $q->whereNull('exam_level')->orWhere('exam_level', $examLevel))
            ->get();
    }

    /**
     * @param  Collection<int, ZimsecValidationRule>  $rules
     * @return array<int, array{field: string, severity: string, message: string}>
     */
    private function fieldErrors(ZimsecCandidate $candidate, Collection $rules): array
    {
        $errors = [];

        foreach ($rules as $rule) {
            $value = $candidate->{$rule->field} ?? null;

            $ok = match ($rule->rule_type) {
                'required' => $value !== null && $value !== '',
                'format' => $value === null || $rule->rule_value === null || preg_match($rule->rule_value, (string) $value) === 1,
                'min_count' => is_int($value) ? $value >= (int) $rule->rule_value : true,
                'max_count' => is_int($value) ? $value <= (int) $rule->rule_value : true,
                'allowed_values' => $value === null || in_array((string) $value, explode(',', (string) $rule->rule_value), true),
                default => true,
            };

            if (! $ok) {
                $errors[] = ['field' => $rule->field, 'severity' => $rule->severity, 'message' => $rule->message];
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array{field: string, severity: string, message: string}>
     */
    private function subjectCountErrors(ZimsecRegistration $registration, ZimsecCandidate $candidate): array
    {
        if ($registration->examination_session_id === null) {
            return [];
        }

        $entry = ExaminationCandidate::where('school_id', $registration->school_id)
            ->where('session_id', $registration->examination_session_id)
            ->where('student_id', $candidate->student_id)
            ->first();

        if ($entry === null) {
            return [];
        }

        $student = Student::find($candidate->student_id);
        $subjectIds = collect($entry->entered_subjects);
        $frameworkId = Subject::withoutGlobalScopes()->whereIn('id', $subjectIds)->first()?->framework_id;

        if ($student === null || $frameworkId === null) {
            return [];
        }

        $result = $this->ruleEngine->validate(
            $subjectIds,
            $frameworkId,
            $student->grade_level_id,
            $student->pathway,
            $registration->school_id,
        );

        $errors = [];

        foreach ($result->blocks as $violation) {
            $errors[] = ['field' => 'subject_count', 'severity' => 'error', 'message' => $violation->message];
        }

        foreach ($result->warnings as $violation) {
            $errors[] = ['field' => 'subject_count', 'severity' => 'warning', 'message' => $violation->message];
        }

        return $errors;
    }
}
