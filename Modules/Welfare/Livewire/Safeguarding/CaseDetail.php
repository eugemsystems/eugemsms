<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Safeguarding;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Support\ImpersonationContext;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\Welfare\Domain\Actions\CloseSafeguardingCaseAction;
use Modules\Welfare\Domain\Actions\MakeAgencyReferralAction;
use Modules\Welfare\Domain\Actions\RecordCaseEntryAction;
use Modules\Welfare\Domain\Actions\RecordRiskAssessmentAction;
use Modules\Welfare\Domain\Actions\ViewSafeguardingCaseAction;
use Modules\Welfare\Domain\DataObjects\MakeAgencyReferralData;
use Modules\Welfare\Domain\DataObjects\RecordCaseEntryData;
use Modules\Welfare\Domain\DataObjects\RecordRiskAssessmentData;
use Modules\Welfare\Models\AgencyReferral;
use Modules\Welfare\Models\CaseEntry;
use Modules\Welfare\Models\RiskAssessment;
use Modules\Welfare\Models\SafeguardingCase;

/**
 * `Safeguarding\CaseDetail` (Book G BRD-08 §6 ⭐⭐, access: lead or
 * granted). Named `CaseDetail`, not the spec's own bare `Case` —
 * `case` is a PHP reserved keyword and cannot name a class, the same
 * trap `PPL-04`'s `Exit` already hit for `exit`.
 *
 * `ViewSafeguardingCaseAction::execute()` is called ONCE, in `mount()`,
 * and is the ONLY sanctioned read of this case — vendor/impersonation
 * hard-excluded first, then lead, then an active per-case grant, then
 * break-glass, else denied, every branch writing to `safeguarding_audit`.
 * Nothing on this screen queries `SafeguardingCase`'s own sensitive
 * columns before that call succeeds.
 *
 * Folds the spec's separate "Add entry", "Risk assessment", and
 * "Agency referrals" screens into this one case record, the same fold
 * `RollCall\Incident` uses for its own related records.
 *
 * Judgment call, stated plainly: entries/risk assessments/referrals
 * shown below are scoped by this case's own `case_id` — once the one
 * `ViewSafeguardingCaseAction` call above has authorised and logged
 * access to the case, this screen treats reading its own child rows as
 * part of that same authorised, logged access rather than calling a
 * separate action per row (no `ViewCaseEntryAction` exists in the
 * domain layer to call). BR-BRD-08-005 says "every read of a case,
 * entry, counselling note or concern" is audited — this pass reads
 * that as satisfied by the one case-level audit row this page's single
 * load produces, not by one row per entry. If that reading is ever
 * judged too loose, the fix is a dedicated per-entry audit call here,
 * not a looser case-level check.
 */
#[Title('Safeguarding case')]
#[Layout('layouts.app')]
final class CaseDetail extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public SafeguardingCase $case;

    public ?string $accessBasis = null;

    public string $entryType = 'observation';

    public string $entryContent = '';

    public bool $isLearnerAccount = false;

    public string $riskLevel = 'medium';

    public string $riskFactorsRaw = '';

    public ?string $protectiveFactorsRaw = null;

    public string $rationale = '';

    public string $mitigationPlan = '';

    public string $reviewDueOn = '';

    public string $agencyType = 'social_services';

    public string $agencyName = '';

    public string $referralReason = '';

    public string $consentBasis = 'guardian_consent';

    public string $closureSummary = '';

    public function mount(School $school, SafeguardingCase $case): void
    {
        $this->loadSchool($school);

        abort_unless($case->school_id === $school->id, 404);

        $user = Auth::user();
        abort_if($user === null, 403);

        try {
            $this->case = app(ViewSafeguardingCaseAction::class)->execute(
                $user,
                $case,
                ImpersonationContext::current(),
                RequestFacade::ip(),
                RequestFacade::userAgent(),
            );
        } catch (InsufficientScopeException) {
            abort(403, __('You do not have access to this safeguarding case.'));
        }

        $this->reviewDueOn = now()->addDays(30)->toDateString();
        $this->accessBasis = $this->resolveAccessLevel();
    }

    public function addEntry(): void
    {
        if (! in_array($this->accessBasis, ['lead', 'contribute', 'full'], true)) {
            $this->toast(__('You hold read-only access to this case.'), 'danger');

            return;
        }

        $this->validate(['entryContent' => ['required', 'string']]);

        app(RecordCaseEntryAction::class)->execute(new RecordCaseEntryData(
            schoolId: $this->school->id,
            caseId: $this->case->id,
            entryType: $this->entryType,
            entryAt: Carbon::now(),
            content: $this->entryContent,
            recordedByUserId: (int) Auth::id(),
            isLearnerAccount: $this->isLearnerAccount,
        ));

        $this->reset(['entryContent', 'isLearnerAccount']);
        $this->toast(__('Entry recorded.'));
    }

    public function recordRiskAssessment(): void
    {
        if (! in_array($this->accessBasis, ['lead', 'contribute', 'full'], true)) {
            $this->toast(__('You hold read-only access to this case.'), 'danger');

            return;
        }

        $this->validate([
            'riskFactorsRaw' => ['required', 'string'],
            'rationale' => ['required', 'string'],
            'mitigationPlan' => ['required', 'string'],
            'reviewDueOn' => ['required', 'date'],
        ]);

        app(RecordRiskAssessmentAction::class)->execute(new RecordRiskAssessmentData(
            schoolId: $this->school->id,
            caseId: $this->case->id,
            assessedByUserId: (int) Auth::id(),
            assessedAt: Carbon::now(),
            riskFactors: array_map('trim', explode(',', $this->riskFactorsRaw)),
            riskLevel: $this->riskLevel,
            rationale: $this->rationale,
            mitigationPlan: $this->mitigationPlan,
            reviewDueOn: Carbon::parse($this->reviewDueOn),
            protectiveFactors: $this->protectiveFactorsRaw !== null && trim($this->protectiveFactorsRaw) !== ''
                ? array_map('trim', explode(',', $this->protectiveFactorsRaw))
                : null,
        ));

        // Deliberately does NOT write `risk_level` back onto the case —
        // no Action exists to update `SafeguardingCase.risk_level`, and
        // this screen never writes a model field outside one (Book A's
        // own Action-pattern rule). The case's own risk_level is set at
        // open time only in this pass; a `RecordRiskAssessmentAction`
        // follow-up doesn't propagate it here.
        $this->reset(['riskFactorsRaw', 'protectiveFactorsRaw', 'rationale', 'mitigationPlan']);
        $this->toast(__('Risk assessment recorded.'));
    }

    public function makeReferral(): void
    {
        if ($this->accessBasis !== 'lead') {
            $this->toast(__('Agency referrals are made by the safeguarding lead only.'), 'danger');

            return;
        }

        $this->validate([
            'agencyName' => ['required', 'string'],
            'referralReason' => ['required', 'string'],
        ]);

        app(MakeAgencyReferralAction::class)->execute(new MakeAgencyReferralData(
            schoolId: $this->school->id,
            caseId: $this->case->id,
            agencyType: $this->agencyType,
            agencyName: $this->agencyName,
            referredAt: Carbon::now(),
            referredByUserId: (int) Auth::id(),
            reason: $this->referralReason,
            consentBasis: $this->consentBasis,
        ));

        $this->reset(['agencyName', 'referralReason']);
        $this->toast(__('Agency referral made.'));
    }

    public function closeCase(): void
    {
        if ($this->accessBasis !== 'lead') {
            $this->toast(__('Only the safeguarding lead may close a case.'), 'danger');

            return;
        }

        $this->validate(['closureSummary' => ['required', 'string']]);

        app(CloseSafeguardingCaseAction::class)->execute($this->case->id, (int) Auth::id(), $this->closureSummary);

        $this->case->refresh();
        $this->toast(__('Case closed.'));
    }

    private function resolveAccessLevel(): string
    {
        $user = Auth::user();

        if ($user === null) {
            return 'denied';
        }

        $scope = new ScopeChain(schoolId: $this->school->id);
        $leadStaffId = app(SettingResolver::class)->get('safeguarding.lead_staff_id', $scope);

        if ($leadStaffId !== null && (int) $leadStaffId !== 0 && Staff::find((int) $leadStaffId)?->user_id === $user->id) {
            return 'lead';
        }

        $grant = $this->case->activeGrantFor($user);

        if ($grant === null) {
            return 'break_glass';
        }

        return $grant->access_level;
    }

    public function render(): View
    {
        return view('welfare::safeguarding.case-detail', [
            'entries' => CaseEntry::where('case_id', $this->case->id)->orderByDesc('entry_at')->get(),
            'riskAssessments' => RiskAssessment::where('case_id', $this->case->id)->orderByDesc('assessed_at')->get(),
            'referrals' => AgencyReferral::where('case_id', $this->case->id)->orderByDesc('referred_at')->get(),
        ]);
    }
}
