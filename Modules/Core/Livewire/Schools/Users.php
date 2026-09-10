<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Schools;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Schools\AssignUserToSchoolAction;
use Modules\Core\Domain\DataObjects\Schools\AssignUserData;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Core\Schools\Users` (Book A CORE-02 §5). Assigns an existing user (by
 * email) to this school and lets a primary be chosen (BR-CORE-02-007).
 * User invitation/creation itself belongs to CORE-05 — this screen only
 * assigns accounts that already exist.
 */
#[Title('School users')]
#[Layout('layouts.app')]
final class Users extends Component
{
    use InteractsWithSchool;
    use Toasts;

    public string $email = '';

    public bool $isPrimary = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function assign(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        $user = User::query()->where('email', $this->email)->first();

        if ($user === null) {
            $this->addError('email', __('No user with that email exists yet.'));

            return;
        }

        app(AssignUserToSchoolAction::class)->execute(new AssignUserData(
            schoolId: $this->school->id,
            userId: $user->id,
            assignedByUserId: (int) Auth::id(),
            isPrimary: $this->isPrimary,
        ));

        $this->reset(['email', 'isPrimary']);

        $this->toast(__('User assigned.'));
    }

    public function makePrimary(int $userId): void
    {
        app(AssignUserToSchoolAction::class)->execute(new AssignUserData(
            schoolId: $this->school->id,
            userId: $userId,
            assignedByUserId: (int) Auth::id(),
            isPrimary: true,
        ));

        $this->toast(__('Primary school updated.'));
    }

    public function render(): View
    {
        return view('core::schools.users', [
            'assignedUsers' => $this->school->users()->orderBy('name')->get(),
        ]);
    }
}
