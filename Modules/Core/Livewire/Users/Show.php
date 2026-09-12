<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Users;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\Actions\Auth\DeactivateUserAction;
use Modules\Core\Domain\Actions\Auth\ResetPasswordAction;
use Modules\Core\Domain\Actions\Auth\RevokeRoleAction;
use Modules\Core\Domain\Actions\Auth\RevokeTokenAction;
use Modules\Core\Domain\DataObjects\Auth\DeactivateUserData;
use Modules\Core\Domain\DataObjects\Auth\ResetPasswordData;
use Modules\Core\Domain\DataObjects\Auth\RevokeTokenData;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Models\LoginAttempt;
use Modules\Core\Models\ModelHasRole;
use Modules\Core\Models\PersonalAccessToken;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;
use Modules\Core\Models\UserAccountLink;

/**
 * `Core\Users\Show` (Book A CORE-05 §6). Roles are read straight from
 * `model_has_roles`/`roles`/`schools` (the same approach
 * `TwoFactorRequirement` uses) rather than `$user->roles`/`$user->schools`
 * — spatie's `roles()` relation is scoped to whatever `SchoolContext`
 * happens to be ambient, but this screen is tenant-wide and must show
 * every school's role assignments at once. `UserAccountLink` is read via
 * `withoutGlobalScopes()` for the same reason: its `BelongsToSchool`
 * scope would otherwise silently filter to one ambient school instead of
 * every school the user is linked in. Permission gating not yet
 * enforced — see `Users\Index`'s docblock.
 *
 * `assignRole()`/`removeRole()` (2026-09-12, user-requested — "then the
 * user can create roles then use the coded permissions so that they can
 * control what their users do") wire the long-unused `AssignRoleAction`/
 * `RevokeRoleAction` to a real screen for the first time; `$availableSchools`
 * is deliberately the ACTING admin's own schools (`Auth::user()->schools()`,
 * same source `Schools\Index` uses), not the target user's — an admin can
 * only grant a role in a school they themselves can operate in. See
 * `Users\DirectPermissions` for granting one permission straight to this
 * user without a role.
 */
#[Title('User')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use Toasts;

    public User $user;

    public bool $showAssignRoleModal = false;

    public ?int $assignRoleSchoolId = null;

    public ?int $assignRoleId = null;

    public function mount(User $user): void
    {
        abort_unless($user->tenant_id === Auth::user()?->tenant_id, 403);

        $this->user = $user;
    }

    public function openAssignRoleModal(): void
    {
        $this->assignRoleSchoolId = null;
        $this->assignRoleId = null;
        $this->showAssignRoleModal = true;
        $this->resetErrorBag();
    }

    public function assignRole(): void
    {
        $this->validate([
            'assignRoleSchoolId' => ['required', 'integer'],
            'assignRoleId' => ['required', 'integer'],
        ]);

        $this->authorizePermission('core.role.update', schoolId: (int) $this->assignRoleSchoolId);

        app(AssignRoleAction::class)->execute(new RoleAssignmentData(
            userId: $this->user->id,
            roleId: (int) $this->assignRoleId,
            schoolId: (int) $this->assignRoleSchoolId,
            performedByUserId: (int) Auth::id(),
        ));

        $this->showAssignRoleModal = false;
        $this->toast(__('Role assigned.'));
    }

    public function removeRole(int $roleId, int $schoolId): void
    {
        $this->authorizePermission('core.role.update', schoolId: $schoolId);

        app(RevokeRoleAction::class)->execute(new RoleAssignmentData(
            userId: $this->user->id,
            roleId: $roleId,
            schoolId: $schoolId,
            performedByUserId: (int) Auth::id(),
        ));

        $this->toast(__('Role removed.'));
    }

    public function resetPassword(): void
    {
        $newPassword = Str::password(16);

        try {
            app(ResetPasswordAction::class)->execute(new ResetPasswordData(
                userId: $this->user->id,
                newPassword: $newPassword,
                resetByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Password reset. The user must set a new one on next login.'));
    }

    public function revokeToken(int $tokenId): void
    {
        // Query the bound Core `PersonalAccessToken` model explicitly
        // rather than through `$this->user->tokens()` — that relation is
        // typed against Sanctum's own base `PersonalAccessToken` class in
        // `HasApiTokens`'s PHPDoc, which does not carry the `isRevoked()`
        // helper this module's extended model adds, even though it
        // resolves to the same bound class at runtime (see
        // `Profile\Devices::revoke()` for the same pattern).
        $token = PersonalAccessToken::query()
            ->where('tokenable_type', $this->user->getMorphClass())
            ->where('tokenable_id', $this->user->id)
            ->find($tokenId);

        if ($token === null || $token->isRevoked()) {
            return;
        }

        try {
            app(RevokeTokenAction::class)->execute(new RevokeTokenData(
                tokenId: $tokenId,
                revokedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Device revoked.'));
    }

    public function deactivate(): void
    {
        try {
            $this->user = app(DeactivateUserAction::class)->execute(new DeactivateUserData(
                userId: $this->user->id,
                deactivatedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('User deactivated.'));
    }

    public function render(): View
    {
        $user = $this->user->fresh();

        if ($user !== null) {
            $this->user = $user;
        }

        $roleAssignments = ModelHasRole::query()
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->leftJoin('schools', 'schools.id', '=', 'model_has_roles.school_id')
            ->where('model_has_roles.model_id', $this->user->id)
            ->where('model_has_roles.model_type', $this->user->getMorphClass())
            ->select(['roles.id as role_id', 'roles.display_name as role_name', 'schools.id as school_id', 'schools.name as school_name'])
            ->orderBy('schools.name')
            ->get();

        $availableSchools = School::query()
            ->whereIn('id', Auth::user()?->schools()->pluck('schools.id') ?? [])
            ->orderBy('name')
            ->get();

        // System templates excluded (2026-09-12, user-requested — same
        // rule as `Users\Form`'s own create-time picker): a role handed
        // out here must already be one the school controls the
        // permissions of, never the shared vendor template itself.
        $availableRoles = Role::query()
            ->where('is_system', false)
            ->whereIn('school_id', $availableSchools->pluck('id'))
            ->orderBy('display_name')
            ->get();

        $devices = $this->user->tokens()->whereNull('revoked_at')->orderByDesc('last_used_at')->get();

        $loginHistory = LoginAttempt::where('user_id', $this->user->id)
            ->latest('attempted_at')
            ->limit(20)
            ->get();

        /** @var Collection<int, UserAccountLink> $linkedRecords */
        $linkedRecords = UserAccountLink::withoutGlobalScopes()
            ->with('school')
            ->where('user_id', $this->user->id)
            ->get();

        return view('core::users.show', [
            'roleAssignments' => $roleAssignments,
            'availableSchools' => $availableSchools,
            'availableRoles' => $availableRoles,
            'devices' => $devices,
            'loginHistory' => $loginHistory,
            'linkedRecords' => $linkedRecords,
        ]);
    }
}
