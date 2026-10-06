<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Models\Term;
use Modules\People\Models\Department;
use Modules\People\Models\Staff;

/**
 * Who a supervision screen may show (Book K ACA-11): a teacher sees their own
 * record; a head of department sees the departments they head; whoever holds
 * `supervision.view` at school reach sees everyone. The staff record is found
 * server-side from the signed-in user, never from anything the browser sends.
 */
trait ResolvesSupervisionReach
{
    use ChecksPermissions;

    protected function ownStaff(): ?Staff
    {
        $userId = Auth::id();

        return $userId === null ? null : Staff::query()->where('user_id', $userId)->first();
    }

    protected function seesEveryone(): bool
    {
        return $this->holds('supervision.view', PermissionScope::School);
    }

    /**
     * Staff ids the user may look at, or null for every member of staff.
     *
     * @return array<int, int>|null
     */
    protected function visibleStaffIds(): ?array
    {
        if ($this->seesEveryone()) {
            return null;
        }

        $own = $this->ownStaff();

        if ($own === null) {
            return [];
        }

        $ids = [$own->id];

        if ($this->holds('supervision.view')) {
            $departmentIds = Department::query()->where('head_staff_id', $own->id)->pluck('id');
            $ids = array_merge($ids, Staff::query()->whereIn('department_id', $departmentIds)->pluck('id')->all());
        }

        return array_values(array_unique($ids));
    }

    protected function mayViewStaff(int $staffId): bool
    {
        $visible = $this->visibleStaffIds();

        return $visible === null || in_array($staffId, $visible, true);
    }

    protected function currentTermId(): ?int
    {
        return Term::query()->where('starts_on', '<=', now())->orderByDesc('starts_on')->value('id');
    }
}
