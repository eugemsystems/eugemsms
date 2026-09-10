<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Domain\Events\WorkloadExceeded;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffWorkload;
use Modules\People\Models\Student;
use Modules\People\Models\TeacherAllocation;

/**
 * ACT-RecalculateStaffWorkload (Book C PPL-04 §3 ⭐/BR-PPL-04-008).
 * Follows the spec's worked pseudocode exactly, with two documented
 * simplifications: `duty_count` is always 0 (duty rosters are
 * deferred — see the module's scope note) and `learner_count` counts
 * `students.status = 'active'` per class directly rather than through
 * a `SchoolClass::active_learner_count` accessor, since no such
 * accessor exists on that Core model and adding one is out of scope
 * for this pass.
 */
final class RecalculateStaffWorkloadAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $staffId, int $termId): StaffWorkload
    {
        $staff = Staff::findOrFail($staffId);

        $allocations = TeacherAllocation::where('staff_id', $staffId)
            ->where('term_id', $termId)
            ->where('status', 'active')
            ->get();

        $periods = (int) $allocations->sum('weekly_periods');
        $ceiling = $staff->max_weekly_periods
            ?? (int) $this->settings->get('staff.default_max_weekly_periods', new ScopeChain(schoolId: $staff->school_id));

        $learnerCount = $allocations->unique('class_id')->sum(
            fn (TeacherAllocation $allocation): int => Student::where('class_id', $allocation->class_id)->where('status', 'active')->count(),
        );

        return $this->transaction(function () use ($staff, $termId, $periods, $ceiling, $learnerCount, $allocations): StaffWorkload {
            $workload = StaffWorkload::updateOrCreate(
                ['staff_id' => $staff->id, 'term_id' => $termId],
                [
                    'school_id' => $staff->school_id,
                    'teaching_periods' => $periods,
                    'subject_count' => $allocations->unique('subject_id')->count(),
                    'class_count' => $allocations->unique('class_id')->count(),
                    'learner_count' => $learnerCount,
                    'class_teacher_count' => $allocations->where('is_class_teacher', true)->count(),
                    'duty_count' => 0,
                    'utilisation_percent' => $ceiling > 0 ? round($periods / $ceiling * 100, 2) : null,
                    'is_overloaded' => $periods > $ceiling,
                    'recalculated_at' => Carbon::now(),
                ],
            );

            if ($workload->is_overloaded) {
                event(new WorkloadExceeded($workload));
            }

            return $workload;
        });
    }
}
