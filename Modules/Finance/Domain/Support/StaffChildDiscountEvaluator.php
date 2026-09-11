<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * Book K FIN-07 §4/BR-FIN-07-004. A guardian IS a staff member when
 * their `Guardian.user_id` matches a `Staff.user_id` — the only link
 * between the two that already exists, no new column needed. A staff
 * member's exit doesn't end the discount immediately: it survives
 * `finance.staff_child_discount_notice_days` past `exited_on`.
 */
final class StaffChildDiscountEvaluator
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function isEligible(Student $student, int $schoolId): bool
    {
        $guardianUserIds = StudentGuardian::withoutGlobalScopes()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('guardian', fn ($q) => $q->whereNotNull('user_id'))
            ->with('guardian:id,user_id')
            ->get()
            ->pluck('guardian.user_id')
            ->filter();

        if ($guardianUserIds->isEmpty()) {
            return false;
        }

        $staffMembers = Staff::withoutGlobalScopes()->whereIn('user_id', $guardianUserIds)->get(['status', 'exited_on']);

        if ($staffMembers->isEmpty()) {
            return false;
        }

        $noticeDays = (int) $this->settings->get('finance.staff_child_discount_notice_days', new ScopeChain(schoolId: $schoolId));

        return $staffMembers->contains(function (Staff $staff) use ($noticeDays): bool {
            if ($staff->exited_on === null) {
                return true;
            }

            return Carbon::today()->lessThanOrEqualTo($staff->exited_on->copy()->addDays($noticeDays));
        });
    }
}
