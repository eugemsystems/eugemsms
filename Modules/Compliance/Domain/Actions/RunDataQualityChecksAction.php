<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Compliance\Models\DataQualityCheck;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * ACT-RunDataQualityChecks (Book H3 CMP-02 §3 ⭐/BR-CMP-02-002
 * (AC-CMP-02-001)). Runs before every return generation — the fields
 * that cause Ministry rejection, caught here first. `missing_dob`
 * always reports zero in this codebase: `students.date_of_birth` is
 * `NOT NULL` at the schema level (Book C PPL-01), a stronger guarantee
 * than the spec's own check assumes — kept as a real, harmless query
 * rather than silently dropped, so the check list stays complete.
 * `staff_missing_qualification` is a proxy on `teacher_registration_no`:
 * `Staff`'s own docblock defers `staff_qualifications` entirely, so
 * there is no qualification record to check yet.
 */
final class RunDataQualityChecksAction extends Action
{
    /**
     * @return Collection<int, DataQualityCheck>
     */
    public function execute(int $schoolId): Collection
    {
        $checks = [
            $this->missingNationalReg($schoolId),
            $this->missingDob($schoolId),
            $this->unallocatedClass($schoolId),
            $this->staffMissingQualification($schoolId),
        ];

        return $this->transaction(function () use ($schoolId, $checks): Collection {
            return collect($checks)->map(fn (array $check): DataQualityCheck => DataQualityCheck::updateOrCreate(
                ['school_id' => $schoolId, 'check_key' => $check['check_key']],
                [
                    'entity_type' => $check['entity_type'],
                    'affected_count' => count($check['affected_ids']),
                    'affected_ids' => $check['affected_ids'] === [] ? null : $check['affected_ids'],
                    'severity' => $check['severity'],
                    'last_checked_at' => Carbon::now(),
                ],
            ));
        });
    }

    /**
     * @return array{check_key: string, entity_type: string, affected_ids: array<int, int>, severity: string}
     */
    private function missingNationalReg(int $schoolId): array
    {
        return [
            'check_key' => 'missing_national_reg',
            'entity_type' => 'student',
            'affected_ids' => Student::where('school_id', $schoolId)->where('status', 'active')->whereNull('national_registration_no')->pluck('id')->all(),
            'severity' => 'error',
        ];
    }

    /**
     * @return array{check_key: string, entity_type: string, affected_ids: array<int, int>, severity: string}
     */
    private function missingDob(int $schoolId): array
    {
        return [
            'check_key' => 'missing_dob',
            'entity_type' => 'student',
            'affected_ids' => Student::where('school_id', $schoolId)->where('status', 'active')->whereNull('date_of_birth')->pluck('id')->all(),
            'severity' => 'error',
        ];
    }

    /**
     * @return array{check_key: string, entity_type: string, affected_ids: array<int, int>, severity: string}
     */
    private function unallocatedClass(int $schoolId): array
    {
        return [
            'check_key' => 'unallocated_class',
            'entity_type' => 'student',
            'affected_ids' => Student::where('school_id', $schoolId)->where('status', 'active')->whereNull('class_id')->pluck('id')->all(),
            'severity' => 'warning',
        ];
    }

    /**
     * @return array{check_key: string, entity_type: string, affected_ids: array<int, int>, severity: string}
     */
    private function staffMissingQualification(int $schoolId): array
    {
        return [
            'check_key' => 'staff_missing_qualification',
            'entity_type' => 'staff',
            'affected_ids' => Staff::where('school_id', $schoolId)->where('status', 'active')->where('is_teaching', true)->whereNull('teacher_registration_no')->pluck('id')->all(),
            'severity' => 'warning',
        ];
    }
}
