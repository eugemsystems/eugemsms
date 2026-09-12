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
use Modules\Core\Domain\Actions\Auth\CreateUserAction;
use Modules\Core\Domain\Actions\Auth\UpdateUserAction;
use Modules\Core\Domain\DataObjects\Auth\CreateUserData;
use Modules\Core\Domain\DataObjects\Auth\UpdateUserData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\UserType;

/**
 * `Core\Users\Form` (Book A CORE-05 §6) — one component for both create
 * and edit, following the route-based "optional model parameter" shape
 * (`users/create` vs `users/{user}/edit`, both routed to this class):
 * `mount(?User $user = null)` receives nothing on the create route and
 * an implicitly-bound `User` on the edit route, and `$editingUserId`
 * being null/non-null is what the rest of the component branches on —
 * the same "one property decides create vs update" shape
 * `Structure\Manager` uses for its own modals. Permission gating not yet
 * enforced — see `Users\Index`'s docblock.
 */
#[Title('User')]
#[Layout('layouts.app')]
final class Form extends Component
{
    use Toasts;

    public ?int $editingUserId = null;

    public string $firstName = '';

    public string $lastName = '';

    public string $otherNames = '';

    public string $email = '';

    public string $phone = '';

    public string $username = '';

    public string $userType = 'staff';

    public string $locale = 'en_ZW';

    public string $password = '';

    public function mount(?User $user = null): void
    {
        if ($user === null) {
            return;
        }

        abort_unless($user->tenant_id === Auth::user()?->tenant_id, 403);

        $this->editingUserId = $user->id;
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
        ]);

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
            }
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast($this->editingUserId !== null ? __('User updated.') : __('User created.'));

        $this->redirectRoute('users.show', ['user' => $user->id], navigate: true);
    }

    public function render(): View
    {
        return view('core::users.form');
    }
}
