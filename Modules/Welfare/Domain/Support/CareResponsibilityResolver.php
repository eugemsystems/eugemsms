<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Support;

use App\Models\User;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Hostel;
use Modules\Core\Models\SchoolClass;
use Modules\People\Models\Student;

/**
 * Book G BRD-06 §3 — `hasCareResponsibility()` resolves to a CURRENT
 * relationship, never a permanent one. This pass resolves it against
 * what actually exists in this codebase: the learner's current class
 * teacher (`SchoolClass.class_teacher_id`) and, for a boarder, their
 * current hostel's housemaster/matron (via the active `bed_allocations`
 * row). Duty-roster and activity-leader responsibility (§3's other two
 * sources) are deferred — no owning table exists yet (`OPS`, not
 * built); catering-staff-for-dietary-only is likewise deferred since
 * this codebase has no catering-staff-role table, only a `catering.*`
 * settings namespace.
 */
final class CareResponsibilityResolver
{
    public function currentlyResponsibleFor(User $user, Student $student): bool
    {
        $classTeacherId = $student->class_id === null
            ? null
            : SchoolClass::find($student->class_id)?->class_teacher_id;

        if ($classTeacherId !== null && $classTeacherId === $user->id) {
            return true;
        }

        $allocation = BedAllocation::query()
            ->where('student_id', $student->id)
            ->where('status', 'confirmed')
            ->whereNull('effective_to')
            ->first();

        if ($allocation === null) {
            return false;
        }

        $hostel = Hostel::find($allocation->hostel_id);

        if ($hostel === null) {
            return false;
        }

        $housemasterUserId = $hostel->housemaster?->user_id;
        $matronUserId = $hostel->matron?->user_id;

        return $user->id === $housemasterUserId || $user->id === $matronUserId;
    }
}
