<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Users;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Auth\DeactivateUserAction;
use Modules\Core\Domain\DataObjects\Auth\DeactivateUserData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\UserStatus;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;

/**
 * `Core\Users\Index` (Book A CORE-05 §6). A user is a tenant-wide
 * identity — `users.tenant_id` is the only boundary here, there is no
 * `{school}` route parameter and no `SchoolContext` involved (unlike
 * almost every other list screen in this app). Authorisation is
 * therefore "any authenticated staff member of this tenant may view/
 * manage its user directory" for now: the `core.user.*` permission
 * catalogue now exists and is seeded (`PermissionRegistry`/
 * `SyncPermissionCatalogueAction`, 2026-09-12), but this screen, `Show`,
 * and `Form` still don't check it — unlike `Roles\*`/`Permissions\Explorer`
 * (which now enforce `core.role.*` via `AuthorizesPermissions`), these
 * three have no single active school to resolve a scope against
 * (`Users\*` is deliberately tenant-wide, no `{school}` route param).
 * `PermissionScopeResolver::has()` takes an explicit `$schoolId` for
 * exactly this shape of screen (see `Users\Show::assignRole()`/
 * `removeRole()`), but "does this user hold `core.user.view` in ANY of
 * their schools" needs a resolver method that doesn't exist yet
 * (`resolve()`/`has()` both check ONE school) — add that before wiring
 * enforcement here, since checking only one arbitrary school would be
 * wrong for a genuinely tenant-wide list.
 */
#[Title('Users')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use InteractsWithDataTable;
    use Toasts;

    public function deactivate(int $userId): void
    {
        $user = User::query()
            ->where('tenant_id', Auth::user()?->tenant_id)
            ->find($userId);

        if ($user === null) {
            return;
        }

        try {
            app(DeactivateUserAction::class)->execute(new DeactivateUserData(
                userId: $user->id,
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
        $query = User::query()
            ->where('tenant_id', Auth::user()?->tenant_id)
            ->orderBy('first_name')
            ->orderBy('last_name');

        return view('core::users.index', [
            'users' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'first_name' => ['label' => __('Name'), 'sortable' => true, 'searchable' => true],
            'email' => ['label' => __('Email'), 'sortable' => true, 'searchable' => true],
            'phone' => ['label' => __('Phone'), 'sortable' => true, 'searchable' => true],
            'username' => ['label' => __('Username'), 'sortable' => true, 'searchable' => true],
            'user_type' => [
                'label' => __('Type'), 'sortable' => true, 'filter' => 'select',
                'options' => $this->userTypeOptions(),
            ],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => $this->userStatusOptions(),
            ],
            'last_login_at' => ['label' => __('Last login'), 'sortable' => true],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function userTypeOptions(): array
    {
        return collect(UserType::cases())
            ->mapWithKeys(fn (UserType $type): array => [$type->value => __(ucfirst($type->value))])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function userStatusOptions(): array
    {
        return collect(UserStatus::cases())
            ->mapWithKeys(fn (UserStatus $status): array => [$status->value => __(ucfirst($status->value))])
            ->all();
    }
}
