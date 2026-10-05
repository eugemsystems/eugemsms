<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Meetings;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\PurgeExpiredRecordingsAction;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Meetings\Recordings` (Book I COM-07 §5, `meetings.recording.view`).
 * Recordings expire and are purged per retention (BR-COM-07-004); the
 * backend job that does so is not scheduled yet, so `meetings.manage`
 * can purge expired ones from here on demand. Purging clears the
 * stored URL only — the meeting record stays.
 */
#[Title('Meeting recordings')]
#[Layout('layouts.app')]
final class Recordings extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('meetings.recording.view');
    }

    public function purgeExpired(): void
    {
        $this->authorizePermission('meetings.manage');

        $count = app(PurgeExpiredRecordingsAction::class)->execute($this->school->id);

        $this->toast(__(':count expired recording(s) purged.', ['count' => $count]));
    }

    public function render(): View
    {
        return view('comms::meetings.recordings', [
            'meetings' => ScheduledMeeting::where('school_id', $this->school->id)
                ->where(fn ($query) => $query->whereNotNull('recording_url')->orWhere('recording_enabled', true))
                ->orderByDesc('starts_at')->limit(100)
                ->get(['id', 'meeting_type', 'starts_at', 'recording_enabled', 'recording_url', 'recording_expires_on']),
            'canPurge' => app(PermissionScopeResolver::class)->has(auth()->user(), 'meetings.manage', PermissionScope::Own),
        ]);
    }
}
