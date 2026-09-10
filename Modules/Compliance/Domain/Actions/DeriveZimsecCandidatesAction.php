<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\SpecialArrangement;
use Modules\Academic\Models\Subject;
use Modules\Compliance\Models\ZimsecCandidate;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Student;

/**
 * ACT-DeriveZimsecCandidates (Book H3 CMP-01 §3 ⭐/BR-CMP-01-001
 * (AC-CMP-01-003)). Pulls every CONFIRMED `examination_candidates` row
 * (Book E ACA-07) for the registration's exam session and copies its
 * bio-data and subject entries straight across — surname, forenames,
 * date of birth, gender, national ID all come from `Student` untouched;
 * `entered_subjects` (local subject IDs, frozen at ACA-07 confirmation)
 * maps to ZIMSEC's own subject codes via `Subject::zimsec_subject_code`.
 * Idempotent: re-running after a new candidate is confirmed only adds
 * the new one, and never overwrites a candidate ACA-07 already
 * accounted for once its bio-data has been copied across.
 */
final class DeriveZimsecCandidatesAction extends Action
{
    /**
     * @return Collection<int, ZimsecCandidate>
     */
    public function execute(int $registrationId): Collection
    {
        $registration = ZimsecRegistration::findOrFail($registrationId);

        if ($registration->examination_session_id === null) {
            throw new InvalidStateTransitionException(
                "ZIMSEC registration #{$registration->id} has no linked examination session to derive candidates from.",
                ['registration_id' => $registration->id],
            );
        }

        $confirmed = ExaminationCandidate::where('school_id', $registration->school_id)
            ->where('session_id', $registration->examination_session_id)
            ->where('entry_status', 'confirmed')
            ->get();

        return $this->transaction(function () use ($registration, $confirmed): Collection {
            $created = new Collection;

            foreach ($confirmed as $entry) {
                $existing = ZimsecCandidate::where('registration_id', $registration->id)
                    ->where('student_id', $entry->student_id)
                    ->first();

                if ($existing !== null) {
                    $created->push($existing);

                    continue;
                }

                $student = Student::findOrFail($entry->student_id);
                $subjects = Subject::withoutGlobalScopes()->whereIn('id', $entry->entered_subjects)->get();

                $candidate = ZimsecCandidate::create([
                    'school_id' => $registration->school_id,
                    'registration_id' => $registration->id,
                    'student_id' => $student->id,
                    'surname' => $student->last_name,
                    'forenames' => trim("{$student->first_name} {$student->middle_names}"),
                    'date_of_birth' => $student->date_of_birth,
                    'gender' => $student->gender,
                    'national_registration_no' => $student->national_registration_no,
                    'national_registration_no_hash' => $student->national_registration_no_hash,
                    'birth_certificate_no' => $student->birth_certificate_no,
                    'birth_certificate_no_hash' => $student->birth_certificate_no_hash,
                    'subject_entries' => $subjects->map(fn (Subject $subject): array => [
                        'code' => $subject->zimsec_subject_code ?? $subject->code,
                        'name' => $subject->name,
                        'is_resit' => false,
                    ])->values()->all(),
                    'subject_count' => $subjects->count(),
                    'special_arrangements' => $this->specialArrangementsFor($registration, $student->id),
                    'entry_fee_minor' => $entry->entry_fee_minor ?? 0,
                    'currency' => $entry->entry_fee_currency ?? $registration->currency,
                    'validation_status' => 'pending',
                    'status' => 'draft',
                ]);

                $created->push($candidate);
            }

            $registration->update(['candidate_count' => ZimsecCandidate::where('registration_id', $registration->id)->count()]);

            return $created;
        });
    }

    /**
     * @return array<int, array{arrangement_type: string, extra_time_percent: int|null}>|null
     */
    private function specialArrangementsFor(ZimsecRegistration $registration, int $studentId): ?array
    {
        $arrangements = SpecialArrangement::where('school_id', $registration->school_id)
            ->where('session_id', $registration->examination_session_id)
            ->where('student_id', $studentId)
            ->where('status', 'approved')
            ->get();

        if ($arrangements->isEmpty()) {
            return null;
        }

        return $arrangements->map(fn (SpecialArrangement $arrangement): array => [
            'arrangement_type' => $arrangement->arrangement_type,
            'extra_time_percent' => $arrangement->extra_time_percent,
        ])->values()->all();
    }
}
