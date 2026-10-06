<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Concerns;

use Modules\Academic\Models\CourseSpace;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\People\Models\Staff;

/**
 * Book K ACA-08 §5. A teacher manages only the course spaces they teach;
 * someone holding the permission at school reach manages all of them. The
 * space id is client-tamperable, so it is re-resolved through the school
 * scope and re-authorised on every call.
 */
trait AuthorizesCourseSpace
{
    protected function authorizeSpace(int $spaceId, string $permission): CourseSpace
    {
        $space = CourseSpace::query()->findOrFail($spaceId);
        $user = auth()->user();

        abort_unless($user !== null, 403);

        $resolver = app(PermissionScopeResolver::class);

        if ($resolver->has($user, $permission, PermissionScope::School)) {
            return $space;
        }

        abort_unless(
            $resolver->has($user, $permission, PermissionScope::Own)
            && $space->teacher_staff_id !== null
            && Staff::query()->where('user_id', $user->id)->whereKey($space->teacher_staff_id)->exists(),
            403,
        );

        return $space;
    }

    protected function currentStaffId(): ?int
    {
        $user = auth()->user();

        return $user === null ? null : Staff::query()->where('user_id', $user->id)->value('id');
    }
}
