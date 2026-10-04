<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Safeguarding;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Welfare\Domain\Actions\OpenSafeguardingCaseAction;
use Modules\Welfare\Domain\Actions\TriageConcernAction;
use Modules\Welfare\Domain\DataObjects\OpenSafeguardingCaseData;
use Modules\Welfare\Livewire\Safeguarding\Concerns\BlocksVendorAndImpersonation;
use Modules\Welfare\Models\SafeguardingConcern;

/**
 * `Safeguarding\Triage` (Book G BRD-08 §6, access: safeguarding lead).
 * Gated on `safeguarding.lead`/`safeguarding.deputy_lead` — the spec's
 * own screen-access column names the role directly here, unlike a
 * SafeguardingCase (where role is only ever candidacy and
 * `ViewSafeguardingCaseAction` is the real gate). No equivalent
 * "ViewSafeguardingConcernAction" exists in the domain layer for a
 * pre-case concern, so the permission check IS the sanctioned
 * enforcement for this screen — the most conservative reading
 * available given what the backend actually provides (see
 * `.ai/rules/welfare.md` for the full reasoning). Every triage
 * decision requires a rationale, including "no further action"
 * (BR-BRD-08-012) — `TriageConcernAction` itself refuses an empty one.
 */
#[Title('Safeguarding triage')]
#[Layout('layouts.app')]
final class Triage extends Component
{
    use AuthorizesPermissions;
    use BlocksVendorAndImpersonation;
    use InteractsWithSchool;
    use Toasts;

    public ?int $triagingConcernId = null;

    public string $triageStatus = 'monitoring';

    public string $rationale = '';

    public ?int $escalatingConcernId = null;

    public string $category = 'other';

    public string $riskLevel = 'medium';

    public string $summary = '';

    public ?bool $guardiansInformed = null;

    public ?string $guardiansNotInformedReason = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->abortIfVendorOrImpersonating();
        $this->authorizeLeadOrDeputy();
    }

    public function triage(int $concernId): void
    {
        if (trim($this->rationale) === '') {
            $this->toast(__('A rationale is mandatory, even for no further action (BR-BRD-08-012).'), 'danger');

            return;
        }

        app(TriageConcernAction::class)->execute($concernId, $this->triageStatus, $this->rationale, (int) Auth::id());

        $this->reset(['triagingConcernId', 'rationale']);
        $this->toast(__('Concern triaged.'));
    }

    public function escalate(int $concernId): void
    {
        $this->validate([
            'summary' => ['required', 'string'],
        ]);

        if ($this->guardiansInformed === false && trim((string) $this->guardiansNotInformedReason) === '') {
            $this->toast(__('A reason is required when guardians are not informed (BR-BRD-08-013).'), 'danger');

            return;
        }

        $concern = SafeguardingConcern::findOrFail($concernId);
        $scope = new ScopeChain(schoolId: $this->school->id);
        $leadStaffId = app(SettingResolver::class)->get('safeguarding.lead_staff_id', $scope);

        if ($leadStaffId === null || (int) $leadStaffId === 0) {
            $this->toast(__('No safeguarding lead is configured for this school yet — set safeguarding.lead_staff_id first.'), 'danger');

            return;
        }

        app(OpenSafeguardingCaseAction::class)->execute(new OpenSafeguardingCaseData(
            schoolId: $this->school->id,
            studentId: (int) $concern->student_id,
            leadStaffId: (int) $leadStaffId,
            openedByUserId: (int) Auth::id(),
            category: $this->category,
            riskLevel: $this->riskLevel,
            summary: $this->summary,
            concernId: $concern->id,
            guardiansInformed: $this->guardiansInformed,
            guardiansNotInformedReason: $this->guardiansNotInformedReason,
        ));

        $this->reset(['escalatingConcernId', 'summary', 'guardiansInformed', 'guardiansNotInformedReason']);
        $this->toast(__('Case opened.'));
    }

    private function authorizeLeadOrDeputy(): void
    {
        $user = Auth::user();

        abort_if($user === null, 403);
        abort_unless($user->hasPermissionTo('safeguarding.lead') || $user->hasPermissionTo('safeguarding.deputy_lead'), 403);
    }

    public function render(): View
    {
        return view('welfare::safeguarding.triage', [
            'concerns' => SafeguardingConcern::where('school_id', $this->school->id)
                ->where('triage_status', 'awaiting_triage')
                ->orderByDesc('immediate_risk')
                ->orderBy('reported_at')
                ->get(),
        ]);
    }
}
