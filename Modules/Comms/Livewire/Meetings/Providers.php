<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Meetings;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\RegisterMeetingProviderAction;
use Modules\Comms\Models\MeetingProvider;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Meetings\Providers` (Book I COM-07 §5, `meetings.manage` ⚠⚠).
 * Credentials are write-only: the form accepts a JSON blob and the list
 * selects only non-secret columns. Registering a provider the school
 * already has *replaces* its credentials and reactivates it (the
 * Action upserts on school + provider) — the screen says so. The
 * webhook secret has no write path in the backend Action, so none is
 * offered here.
 */
#[Title('Meeting providers')]
#[Layout('layouts.app')]
final class Providers extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $provider = 'zoom';

    public string $credentials = '';

    public string $accountEmail = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('meetings.manage');
    }

    public function register(): void
    {
        $this->authorizePermission('meetings.manage');

        $this->validate([
            'provider' => ['required', 'in:zoom,google_meet,teams'],
            'credentials' => ['required', 'json'],
            'accountEmail' => ['nullable', 'email', 'max:150'],
        ]);

        app(RegisterMeetingProviderAction::class)->execute(
            $this->school->id,
            $this->provider,
            $this->credentials,
            $this->accountEmail !== '' ? $this->accountEmail : null,
        );

        $this->reset(['credentials', 'accountEmail']);
        $this->toast(__('Provider saved. Any previous credentials for it were replaced.'));
    }

    public function render(): View
    {
        return view('comms::meetings.providers', [
            'providers' => MeetingProvider::where('school_id', $this->school->id)->orderBy('provider')->get(['id', 'provider', 'account_email', 'is_active', 'updated_at']),
        ]);
    }
}
