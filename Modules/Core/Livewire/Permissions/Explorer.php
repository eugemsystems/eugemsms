<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Permissions;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ModelHasRole;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\RolePermissionScope;
use Modules\Core\Models\School;

/**
 * `Core\Permissions\Explorer` (Book A CORE-05 §6, `core.role.view`). The
 * "who can do X?" reverse lookup, read literally: pick a permission and
 * see every role usable in this school (school-owned + system templates,
 * same visibility rule as `Core\Roles\Index`) that grants it, each with
 * its scope and how many users in this school currently hold that role.
 * A secondary "By user" tab answers the mirror question — pick a user
 * and see every permission they effectively hold in this school, via
 * `PermissionScopeResolver` (BR-CORE-05-014/015 — the widest scope
 * across all their roles wins).
 */
#[Title('Permission explorer')]
#[Layout('layouts.app')]
final class Explorer extends Component
{
    use InteractsWithSchool;

    #[Url(as: 'mode', history: true)]
    public string $mode = 'permission';

    #[Url(as: 'permission', history: true)]
    public ?int $selectedPermissionId = null;

    #[Url(as: 'user', history: true)]
    public ?int $selectedUserId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function selectMode(string $mode): void
    {
        $this->mode = in_array($mode, ['permission', 'user'], true) ? $mode : 'permission';
    }

    public function render(): View
    {
        $permissions = Permission::query()->orderBy('module_code')->orderBy('resource')->orderBy('action')->get();

        return view('core::permissions.explorer', [
            'permissions' => $permissions,
            'grants' => $this->grantsForSelectedPermission(),
            'users' => $this->usersInSchool(),
            'userPermissions' => $this->permissionsForSelectedUser($permissions),
        ]);
    }

    /**
     * @return Collection<int, array{role: Role, scope: PermissionScope, holders: int}>
     */
    private function grantsForSelectedPermission(): Collection
    {
        if ($this->selectedPermissionId === null) {
            return collect();
        }

        $grants = RolePermissionScope::query()
            ->with('role')
            ->where('permission_id', $this->selectedPermissionId)
            ->whereHas('role', function ($query): void {
                $query->where('school_id', $this->school->id)->orWhereNull('school_id');
            })
            ->get();

        $rows = [];

        foreach ($grants as $grant) {
            $role = $grant->role;

            // `role` is a plain BelongsTo — PHPStan can't see that the
            // whereHas() above guarantees it resolves, so this stays an
            // explicit local-variable guard rather than a nullsafe chain
            // (Modules/Core rule: don't trust Larastan's "always non-null"
            // inference here either way — verify with a real if-guard).
            if ($role === null) {
                continue;
            }

            $rows[] = [
                'role' => $role,
                'scope' => $grant->scope,
                'holders' => ModelHasRole::query()
                    ->where('role_id', $grant->role_id)
                    ->where('school_id', $this->school->id)
                    ->count(),
            ];
        }

        return collect($rows)->sortByDesc(fn (array $row): int => $row['holders'])->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function usersInSchool(): Collection
    {
        return User::query()
            ->whereHas('schools', function ($query): void {
                $query->where('schools.id', $this->school->id)->where('school_user.status', 'active');
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return Collection<int, array{permission: Permission, scope: PermissionScope}>
     */
    private function permissionsForSelectedUser(Collection $permissions): Collection
    {
        if ($this->selectedUserId === null) {
            return collect();
        }

        $user = User::find($this->selectedUserId);

        if ($user === null) {
            return collect();
        }

        $resolver = app(PermissionScopeResolver::class);
        $rows = [];

        foreach ($permissions as $permission) {
            $scope = $resolver->resolve($user, $permission->name);

            if ($scope === null) {
                continue;
            }

            $rows[] = ['permission' => $permission, 'scope' => $scope];
        }

        return collect($rows);
    }
}
