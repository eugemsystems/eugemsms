<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Domain\DataObjects\AllocateTeacherData;
use Modules\People\Domain\Events\TeacherAllocated;
use Modules\People\Domain\Exceptions\DuplicateClassTeacherException;
use Modules\People\Domain\Exceptions\NotATeachingStaffException;
use Modules\People\Domain\Exceptions\WorkloadCeilingExceededException;
use Modules\People\Models\Staff;
use Modules\People\Models\TeacherAllocation;

/**
 * ACT-AllocateTeacher (Book C PPL-04 §4/BR-PPL-04-005/006/007,
 * AC-PPL-04-001/002). The qualification check BR-PPL-04-005 also
 * names ("warning, not block") is skipped — `staff_qualifications` is
 * deferred along with the rest of this module's documents/appraisal
 * tables. `is_teaching` and the one-class-teacher-per-class rule are
 * both hard blocks with no override, matching the spec's literal
 * wording; only the workload ceiling has an override path, per
 * AC-PPL-04-001.
 */
final class AllocateTeacherAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly RecalculateStaffWorkloadAction $recalculateWorkload,
    ) {}

    public function execute(AllocateTeacherData $data): TeacherAllocation
    {
        $staff = Staff::findOrFail($data->staffId);

        if (! $staff->is_teaching) {
            throw NotATeachingStaffException::forStaff($staff->id);
        }

        if ($data->isClassTeacher) {
            $existingClassTeacher = TeacherAllocation::where('school_id', $data->schoolId)
                ->where('term_id', $data->termId)
                ->where('class_id', $data->classId)
                ->where('is_class_teacher', true)
                ->where('status', 'active')
                ->exists();

            if ($existingClassTeacher) {
                throw DuplicateClassTeacherException::forClass($data->classId, $data->termId);
            }
        }

        $currentPeriods = (int) TeacherAllocation::where('staff_id', $staff->id)
            ->where('term_id', $data->termId)
            ->where('status', 'active')
            ->sum('weekly_periods');

        $ceiling = $staff->max_weekly_periods
            ?? (int) $this->settings->get('staff.default_max_weekly_periods', new ScopeChain(schoolId: $data->schoolId));

        if ($currentPeriods + $data->weeklyPeriods > $ceiling) {
            $enforced = (bool) $this->settings->get('staff.enforce_workload_ceiling', new ScopeChain(schoolId: $data->schoolId));

            if ($enforced && ! $data->overrideCeiling) {
                throw WorkloadCeilingExceededException::forStaff($staff->id, $currentPeriods, $data->weeklyPeriods, $ceiling);
            }
        }

        return $this->transaction(function () use ($staff, $data): TeacherAllocation {
            $allocation = TeacherAllocation::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'staff_id' => $staff->id,
                'subject_id' => $data->subjectId,
                'class_id' => $data->classId,
                'role' => $data->role,
                'weekly_periods' => $data->weeklyPeriods,
                'is_class_teacher' => $data->isClassTeacher,
                'starts_on' => ($data->startsOn ?? Carbon::now())->toDateString(),
                'status' => 'active',
                'allocated_by' => $data->allocatedByUserId,
            ]);

            $this->recalculateWorkload->execute($staff->id, $data->termId);

            event(new TeacherAllocated($allocation));

            return $allocation;
        });
    }
}
