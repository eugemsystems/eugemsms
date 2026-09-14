<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\DataObjects\Schools\AssignUserData;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;

/**
 * ACT-AssignUserToSchool (Book A CORE-02 §3). BR-CORE-02-007: a user may
 * be assigned to many schools but has exactly one `is_primary = 1` — the
 * new assignment unsets it on every other school when requested primary.
 *
 * Spatie's own role/permission checks are team-scoped by school
 * (`AssignRoleAction`'s own docblock) — being attached to a school via
 * `school_user` grants no permissions there by itself; a role still has
 * to be assigned for that specific school in `model_has_roles`. A Super
 * Admin is not exempt from this (confirmed empirically, 2026-09-13:
 * `PermissionScopeResolver` has no super-admin bypass anywhere), so
 * without the block below, attaching an existing Super Admin to a new
 * school would silently leave them 403'd on every permission-gated
 * screen there. If the user already holds `super_admin` in at least one
 * school, this carries that same role assignment over to the newly
 * attached school too, so a platform-wide administrator stays one
 * everywhere they're attached, without needing a separate manual
 * role-assignment step every time.
 */
final class AssignUserToSchoolAction extends Action
{
    public function execute(AssignUserData $data): void
    {
        Validator::make(
            ['school_id' => $data->schoolId, 'user_id' => $data->userId],
            [
                'school_id' => ['required', 'integer', 'exists:schools,id'],
                'user_id' => ['required', 'integer', 'exists:users,id'],
            ],
        )->validate();

        $this->transaction(function () use ($data): void {
            $school = School::query()->findOrFail($data->schoolId);
            $user = User::query()->findOrFail($data->userId);

            if ($data->isPrimary) {
                DB::table('school_user')->where('user_id', $user->id)->update(['is_primary' => false]);
            }

            $school->users()->syncWithoutDetaching([
                $user->id => [
                    'is_primary' => $data->isPrimary,
                    'status' => 'active',
                    'assigned_at' => now(),
                    'assigned_by' => $data->assignedByUserId,
                ],
            ]);

            $this->carrySuperAdminRoleToSchool($user, $school->id, $data->assignedByUserId);
        });
    }

    private function carrySuperAdminRoleToSchool(User $user, int $schoolId, int $assignedByUserId): void
    {
        $superAdminRole = Role::query()->where('name', 'super_admin')->whereNull('school_id')->first();

        if ($superAdminRole === null) {
            return;
        }

        $alreadySuperAdminSomewhere = DB::table('model_has_roles')
            ->where('role_id', $superAdminRole->id)
            ->where('model_id', $user->id)
            ->exists();

        $alreadySuperAdminHere = DB::table('model_has_roles')
            ->where('role_id', $superAdminRole->id)
            ->where('model_id', $user->id)
            ->where('school_id', $schoolId)
            ->exists();

        if ($alreadySuperAdminSomewhere && ! $alreadySuperAdminHere) {
            app(AssignRoleAction::class)->execute(new RoleAssignmentData(
                userId: $user->id,
                roleId: $superAdminRole->id,
                schoolId: $schoolId,
                performedByUserId: $assignedByUserId,
            ));
        }
    }
}
