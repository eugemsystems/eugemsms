<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Users;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\UserPermissionScope;

/**
 * `Core\Users\DirectPermissions` (Book A CORE-05 §2 extension,
 * 2026-09-12, user-requested: "i can also do direct permissions to a
 * certain user not on a role"). The mirror of `Core\Roles\Editor`'s
 * permission matrix, except it grants straight to one user in one
 * school rather than to a role — for the case where an admin needs to
 * hand someone one extra permission (or take one away) without cloning
 * or editing a whole role. A user's *effective* permissions are always
 * role grants ∪ these direct grants, widest scope wins
 * (`PermissionScopeResolver`).
 *
 * Gated on `core.role.update` — granting permissions is at least as
 * sensitive as editing a role's own permission set.
 */
#[Title('Direct Permissions')]
#[Layout('layouts.app')]
final class DirectPermissions extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public User $user;

    #[Url(as: 'module', history: true)]
    public string $activeModule = '';

    /**
     * @var array<int, bool>
     */
    public array $granted = [];

    /**
     * @var array<int, string>
     */
    public array $scopes = [];

    public function mount(School $school, User $user): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.role.update');

        abort_unless($user->tenant_id === Auth::user()?->tenant_id, 403);

        $this->user = $user;

        foreach ($user->permissions()->pluck('permissions.id') as $permissionId) {
            $this->granted[(int) $permissionId] = true;
        }

        foreach (UserPermissionScope::where('user_id', $user->id)->where('school_id', $school->id)->pluck('scope', 'permission_id') as $permissionId => $scope) {
            $this->scopes[(int) $permissionId] = $scope instanceof PermissionScope ? $scope->value : (string) $scope;
        }
    }

    public function setActiveModule(string $moduleCode): void
    {
        $this->activeModule = $moduleCode;
    }

    public function toggleGrant(int $permissionId): void
    {
        $isGranted = ! ($this->granted[$permissionId] ?? false);
        $this->granted[$permissionId] = $isGranted;

        if ($isGranted) {
            $this->scopes[$permissionId] ??= PermissionScope::Own->value;
        }
    }

    public function save(): void
    {
        $grants = [];

        foreach ($this->granted as $permissionId => $isGranted) {
            if (! $isGranted) {
                continue;
            }

            $scope = PermissionScope::tryFrom($this->scopes[$permissionId] ?? '') ?? PermissionScope::Own;

            $grants[] = new PermissionGrantData((int) $permissionId, $scope);
        }

        app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
            userId: $this->user->id,
            schoolId: $this->school->id,
            grants: $grants,
            updatedByUserId: (int) Auth::id(),
        ));

        $this->toast(__('Direct permissions updated.'));
    }

    public function render(): View
    {
        $modules = Permission::query()->distinct()->orderBy('module_code')->pluck('module_code');

        if ($this->activeModule === '' || ! $modules->contains($this->activeModule)) {
            $this->activeModule = (string) ($modules->first() ?? '');
        }

        $moduleCounts = Permission::query()
            ->selectRaw('module_code, count(*) as aggregate')
            ->groupBy('module_code')
            ->pluck('aggregate', 'module_code');

        $permissions = Permission::query()
            ->where('module_code', $this->activeModule)
            ->orderBy('resource')
            ->orderBy('action')
            ->get();

        return view('core::users.direct-permissions', [
            'modules' => $modules,
            'moduleCounts' => $moduleCounts,
            'permissions' => $permissions,
            'scopeOptions' => PermissionScope::cases(),
        ]);
    }
}
