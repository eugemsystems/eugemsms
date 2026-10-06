<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Guardians;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\GrantGuardianPortalAccessAction;
use Modules\People\Domain\Actions\RevokeGuardianPortalAccessAction;
use Modules\People\Models\Guardian;

/**
 * `People\Guardians\PortalAccess` (Book C PPL-03 §6, `people.guardians.portal_access`). Who can use the
 * parent app. Granting makes an account for the guardian's phone number — they sign in with a code
 * sent to it — and withdrawing signs them out everywhere. A guardian with no phone, no learner at
 * the school, or an account belonging to someone else is refused with the reason.
 */
#[Title('Parent app access')]
#[Layout('layouts.app')]
final class PortalAccess extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;
    use WithPagination;

    public string $search = '';

    public string $filter = 'without';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.guardians.portal_access');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function grant(int $guardianId): void
    {
        $this->authorizePermission('people.guardians.portal_access');

        try {
            app(GrantGuardianPortalAccessAction::class)->execute(Guardian::query()->where('school_id', $this->school->id)->findOrFail($guardianId)->id, (int) auth()->id());
        } catch (ValidationException $e) {
            $this->toast(implode(' ', collect($e->errors())->flatten()->all()), 'danger');

            return;
        }

        $this->toast(__('Parent app access granted. They sign in with a code sent to their phone.'));
    }

    public function revoke(int $guardianId): void
    {
        $this->authorizePermission('people.guardians.portal_access');

        try {
            app(RevokeGuardianPortalAccessAction::class)->execute(Guardian::query()->where('school_id', $this->school->id)->findOrFail($guardianId)->id);
        } catch (InvalidStateTransitionException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Parent app access withdrawn and their devices signed out.'));
    }

    public function render(): View
    {
        $term = trim($this->search);

        return view('people::guardians.portal-access', [
            'guardians' => Guardian::query()->where('school_id', $this->school->id)
                ->when($this->filter === 'with', fn ($q) => $q->whereNotNull('user_id'), fn ($q) => $q->whereNull('user_id'))
                ->when($term !== '', fn ($q) => $q->where(fn ($q2) => $q2->where('last_name', 'like', "%{$term}%")->orWhere('first_name', 'like', "%{$term}%")->orWhere('primary_phone', 'like', "%{$term}%")))
                ->orderBy('last_name')->orderBy('first_name')->paginate(20),
        ]);
    }
}
