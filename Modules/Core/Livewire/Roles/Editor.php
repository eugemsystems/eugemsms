<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Roles;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Domain\Actions\Auth\UpdateRolePermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\RolePermissionData;
use Modules\Core\Domain\Exceptions\AuthorisationException;
use Modules\Core\Domain\Exceptions\SystemRoleTemplateException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;

/**
 * `Core\Roles\Editor` (Book A CORE-05 §6, `core.role.update`, enforced
 * 2026-09-12). The
 * permission matrix: every `Permission` grouped by `module_code` into a
 * tab (mirrors `Core\Settings\Index`'s module-tab pattern), each with a
 * grant checkbox and, once granted, a scope `<select>` — one "Save"
 * collects every checked permission's scope and calls
 * `UpdateRolePermissionsAction` in a single request, rather than saving
 * per-row like the Settings screen does (there is no meaningful
 * "partial" save here — the Action replaces the role's whole grant set).
 *
 * BR-CORE-05-013: a system template's permissions can't be edited
 * directly. Rather than let the form load and silently no-op on Save,
 * `mount()` refuses up front and sends the admin back to the Index with
 * a toast telling them to clone it first.
 */
#[Title('Edit Role')]
#[Layout('layouts.app')]
final class Editor extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Role $role;

    #[Url(as: 'module', history: true)]
    public string $activeModule = '';

    /**
     * Whether each permission (keyed by `Permission::id`) is currently
     * granted — seeded from the role's existing `role_has_permissions`
     * rows in `mount()`, then mutated locally by `toggleGrant()` until
     * `save()` sends the whole set to the Action.
     *
     * @var array<int, bool>
     */
    public array $granted = [];

    /**
     * The selected scope per granted permission (keyed by `Permission::id`,
     * `PermissionScope::value`) — seeded from the role's existing
     * `RolePermissionScope` rows.
     *
     * @var array<int, string>
     */
    public array $scopes = [];

    public function mount(School $school, Role $role): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.role.update');

        if ($role->is_system) {
            $this->toast(__('System role templates cannot be edited directly. Clone it first, then edit the clone.'), 'danger');
            $this->redirect(route('roles.index', $school), navigate: true);

            return;
        }

        $this->role = $role;

        foreach ($role->permissions()->pluck('permissions.id') as $permissionId) {
            $this->granted[(int) $permissionId] = true;
        }

        foreach ($role->permissionScopes()->pluck('scope', 'permission_id') as $permissionId => $scope) {
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

        try {
            app(UpdateRolePermissionsAction::class)->execute(new RolePermissionData(
                roleId: $this->role->id,
                grants: $grants,
                updatedByUserId: (int) Auth::id(),
            ));
        } catch (SystemRoleTemplateException|AuthorisationException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        } catch (ValidationException $e) {
            $this->toast($e->validator->errors()->first(), 'danger');

            return;
        }

        $this->toast(__('Role permissions updated.'));
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

        return view('core::roles.editor', [
            'modules' => $modules,
            'moduleCounts' => $moduleCounts,
            'permissions' => $permissions,
            'scopeOptions' => PermissionScope::cases(),
        ]);
    }
}
