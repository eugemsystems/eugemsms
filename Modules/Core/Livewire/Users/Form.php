<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Users;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\Actions\Auth\CreateUserAction;
use Modules\Core\Domain\Actions\Auth\UpdateUserAction;
use Modules\Core\Domain\DataObjects\Auth\CreateUserData;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\DataObjects\Auth\UpdateUserData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;

/**
 * `Core\Users\Form` (Book A CORE-05 §6) — one component for both create
 * and edit, following the route-based "optional model parameter" shape
 * (`users/create` vs `users/{user}/edit`, both routed to this class):
 * `mount(?User $user = null)` receives nothing on the create route and
 * an implicitly-bound `User` on the edit route, and `$editingUserId`
 * being null/non-null is what the rest of the component branches on —
 * the same "one property decides create vs update" shape
 * `Structure\Manager` uses for its own modals. Baseline `core.user.*`
 * gating not yet enforced — see `Users\Index`'s docblock.
 *
 * `roleSchoolId`/`roleId` (2026-09-12, user-requested — "when i create
 * users should i not choose the role for them"): an optional role
 * assignment made in the same step as creation, rather than forcing a
 * trip to `Users\Show` afterward just to make the new account usable.
 * Create-only (an existing user already has `Show`'s own role-management
 * UI, which handles multiple schools/roles — this form only ever grants
 * one). Reuses `AssignRoleAction` and `Show::assignRole()`'s own
 * "explicit schoolId, `core.role.update`" gate — see `.ai/rules/auth.md`
 * on why a tenant-wide screen like this one can't rely on the ambient
 * school.
 */
#[Title('User')]
#[Layout('layouts.app')]
final class Form extends Component
{
    use AuthorizesPermissions;
    use Toasts;

    public ?int $editingUserId = null;

    /**
     * The route key for the "back"/cancel link on the edit form — a
     * separate property from `$editingUserId` since `route('users.show',
     * ...)` needs the ulid, not the id, and the view has no other way to
     * reach the target user's model (2026-09-12 bugfix, see
     * `Modules\Core\Domain\Concerns\HasUlid`'s docblock).
     */
    public ?string $editingUserUlid = null;

    public string $firstName = '';

    public string $lastName = '';

    public string $otherNames = '';

    public string $email = '';

    public string $phone = '';

    public string $username = '';

    public string $userType = 'staff';

    public string $locale = 'en_ZW';

    public string $password = '';

    public ?int $roleSchoolId = null;

    public ?int $roleId = null;

    public function mount(?User $user = null): void
    {
        if ($user === null) {
            return;
        }

        abort_unless($user->tenant_id === Auth::user()?->tenant_id, 403);

        $this->editingUserId = $user->id;
        $this->editingUserUlid = $user->ulid;
        $this->firstName = (string) $user->first_name;
        $this->lastName = (string) $user->last_name;
        $this->otherNames = (string) $user->other_names;
        $this->email = (string) $user->email;
        $this->phone = (string) $user->phone;
        $this->username = (string) $user->username;
        $this->userType = ($user->user_type ?? UserType::Staff)->value;
        $this->locale = $user->locale ?? 'en_ZW';
    }

    public function save(): void
    {
        $assignsRole = $this->editingUserId === null && ($this->roleSchoolId !== null || $this->roleId !== null);

        $this->validate([
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'otherNames' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'username' => ['nullable', 'string', 'max:60'],
            'userType' => ['required', Rule::enum(UserType::class)],
            'locale' => ['required', 'string', 'max:10'],
            'password' => $this->editingUserId === null ? ['nullable', 'string'] : ['prohibited'],
            'roleSchoolId' => $assignsRole ? ['required', 'integer'] : ['nullable', 'integer'],
            'roleId' => $assignsRole ? ['required', 'integer'] : ['nullable', 'integer'],
        ]);

        if ($assignsRole) {
            $this->authorizePermission('core.role.update', schoolId: (int) $this->roleSchoolId);
        }

        $actingUserId = (int) Auth::id();
        $userType = UserType::from($this->userType);

        try {
            if ($this->editingUserId !== null) {
                $user = app(UpdateUserAction::class)->execute(new UpdateUserData(
                    userId: $this->editingUserId,
                    firstName: $this->firstName,
                    lastName: $this->lastName,
                    otherNames: $this->otherNames !== '' ? $this->otherNames : null,
                    email: $this->email !== '' ? $this->email : null,
                    phone: $this->phone !== '' ? $this->phone : null,
                    username: $this->username !== '' ? $this->username : null,
                    userType: $userType,
                    locale: $this->locale,
                    updatedByUserId: $actingUserId,
                ));
            } else {
                $user = app(CreateUserAction::class)->execute(new CreateUserData(
                    firstName: $this->firstName,
                    lastName: $this->lastName,
                    otherNames: $this->otherNames !== '' ? $this->otherNames : null,
                    email: $this->email !== '' ? $this->email : null,
                    phone: $this->phone !== '' ? $this->phone : null,
                    username: $this->username !== '' ? $this->username : null,
                    password: $this->password !== '' ? $this->password : null,
                    userType: $userType,
                    tenantId: Auth::user()?->tenant_id,
                    locale: $this->locale,
                    createdByUserId: $actingUserId,
                ));

                if ($assignsRole) {
                    app(AssignRoleAction::class)->execute(new RoleAssignmentData(
                        userId: $user->id,
                        roleId: (int) $this->roleId,
                        schoolId: (int) $this->roleSchoolId,
                        performedByUserId: $actingUserId,
                    ));
                }
            }
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast($this->editingUserId !== null ? __('User updated.') : __('User created.'));

        $this->redirectRoute('users.show', ['user' => $user], navigate: true);
    }

    public function render(): View
    {
        $availableSchools = School::query()
            ->whereIn('id', Auth::user()?->schools()->pluck('schools.id') ?? [])
            ->orderBy('name')
            ->get();

        // System templates deliberately excluded here (2026-09-12,
        // user-requested — "i need the admin to be able to control all
        // the permissions for each role so they will clone from the
        // system ones first"): a role assigned at creation must already
        // be one the school controls the permissions of, i.e. a clone
        // (`is_system = false`), never the shared vendor template itself
        // — unlike `Show`'s own assign-role modal, which still offers
        // every role (including system ones) for an EXISTING user, since
        // that screen predates this constraint and covers other cases
        // (e.g. handing out `Super Admin` itself).
        $availableRoles = Role::query()
            ->where('is_system', false)
            ->whereIn('school_id', $availableSchools->pluck('id'))
            ->orderBy('display_name')
            ->get();

        return view('core::users.form', [
            'availableSchools' => $availableSchools,
            'availableRoles' => $availableRoles,
        ]);
    }
}
