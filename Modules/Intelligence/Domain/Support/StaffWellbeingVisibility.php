<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Support;

use App\Models\User;
use Modules\People\Models\Staff;

/**
 * Book J INT-03 §4/BR-INT-03-010. A staff member's wellbeing indicator
 * is visible to that staff member and to their line manager
 * (`reports_to_staff_id`) — nobody else, whatever their permissions.
 * There is deliberately no school-wide override.
 */
final class StaffWellbeingVisibility
{
    /**
     * @return array<int, int> staff ids whose indicators this user may read
     */
    public static function visibleStaffIds(User $user, int $schoolId): array
    {
        $own = Staff::where('school_id', $schoolId)->where('user_id', $user->id)->pluck('id');

        if ($own->isEmpty()) {
            return [];
        }

        $reports = Staff::where('school_id', $schoolId)->whereIn('reports_to_staff_id', $own)->pluck('id');

        return $own->merge($reports)->unique()->values()->all();
    }
}
