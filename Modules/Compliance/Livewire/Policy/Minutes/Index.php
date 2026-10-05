<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Policy\Minutes;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\AccessGovernanceMinuteAction;
use Modules\Compliance\Domain\Actions\CreateGovernanceMinuteAction;
use Modules\Compliance\Domain\DataObjects\CreateGovernanceMinuteData;
use Modules\Compliance\Domain\Exceptions\MinuteAccessDeniedException;
use Modules\Compliance\Models\GovernanceMinute;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Policy\Minutes` (Book H3 CMP-04, `policy.manage`).
 * A `restricted`/`confidential` minute refuses access outright when
 * the user holds none of `access_role_ids` (BR-CMP-04-007) —
 * `AccessGovernanceMinuteAction` itself enforces this and logs every
 * successful access; this screen passes the current user's own role
 * ids straight through rather than re-deriving the check.
 */
#[Title('Governance minutes')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $body = 'board';

    public string $meetingDate = '';

    public string $attendeesText = '';

    public string $confidentiality = 'open';

    public string $resolutionsText = '';

    public ?int $openedMinuteId = null;

    public ?GovernanceMinute $openedMinute = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('policy.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('policy.manage');

        $this->validate([
            'body' => ['required', 'in:board,finance_committee,disciplinary,academic_board'],
            'meetingDate' => ['required', 'date'],
            'attendeesText' => ['required', 'string'],
            'confidentiality' => ['required', 'in:open,restricted,confidential'],
        ]);

        $attendees = array_values(array_filter(array_map('trim', explode(',', $this->attendeesText))));
        $resolutions = $this->resolutionsText !== ''
            ? array_values(array_filter(array_map('trim', explode("\n", $this->resolutionsText))))
            : null;

        app(CreateGovernanceMinuteAction::class)->execute(new CreateGovernanceMinuteData(
            schoolId: $this->school->id,
            body: $this->body,
            meetingDate: $this->meetingDate,
            attendees: $attendees,
            confidentiality: $this->confidentiality,
            resolutions: $resolutions,
        ));

        $this->reset(['attendeesText', 'resolutionsText']);
        $this->toast(__('Minute recorded.'));
    }

    public function open(int $minuteId): void
    {
        $user = auth()->user();
        $roleIds = $user?->roles->pluck('id')->all() ?? [];

        try {
            $this->openedMinute = app(AccessGovernanceMinuteAction::class)->execute($minuteId, (int) auth()->id(), $roleIds);
            $this->openedMinuteId = $minuteId;
        } catch (MinuteAccessDeniedException $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function render(): View
    {
        return view('compliance::policy.minutes.index', [
            'minutes' => GovernanceMinute::where('school_id', $this->school->id)->orderByDesc('meeting_date')->get(),
        ]);
    }
}
