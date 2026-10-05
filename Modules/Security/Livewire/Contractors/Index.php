<?php

declare(strict_types=1);

namespace Modules\Security\Livewire\Contractors;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Security\Domain\Actions\ApproveContractorAction;
use Modules\Security\Domain\Actions\CreateContractorAction;
use Modules\Security\Domain\Actions\CreateContractorWorkerAction;
use Modules\Security\Domain\Actions\SignInContractorWorkerAction;
use Modules\Security\Domain\Actions\SignOutContractorWorkerAction;
use Modules\Security\Domain\DataObjects\ApproveContractorData;
use Modules\Security\Domain\DataObjects\CreateContractorData;
use Modules\Security\Domain\DataObjects\CreateContractorWorkerData;
use Modules\Security\Domain\Exceptions\GateAccessRefusedException;
use Modules\Security\Models\Contractor;
use Modules\Security\Models\ContractorSiteVisit;
use Modules\Security\Models\ContractorWorker;

/**
 * `Contractors\Index` (Book H2 OPS-06 §5 ⭐/BR-OPS-06-001/002,
 * `security.contractor.manage`). Folds the gate sign-in/out for
 * contractor workers into this screen — the only module in this book
 * with its own hard, no-override access-refusal flow outside
 * `BRD-03`'s own gate terminal. This screen offers no override
 * control because `SignInContractorWorkerAction` itself has none: a
 * worker with no police clearance, or whose contractor isn't
 * currently approved with current insurance/induction, is refused
 * outright and the refusal is shown verbatim — the same
 * hard-refusal, no-bypass discipline `Boarding\Gate\Terminal` and
 * `Welfare\Safeguarding\CaseDetail` already established in this
 * codebase.
 */
#[Title('Contractors & gate')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $companyName = '';

    public ?string $contactPerson = null;

    public ?string $phone = null;

    public ?string $workType = null;

    public string $insuranceExpiresOn = '';

    public string $safetyInductionOn = '';

    public string $inductionValidUntil = '';

    public ?string $policeClearanceOn = null;

    public ?int $approvingContractorId = null;

    public ?int $workerContractorId = null;

    public string $workerFullName = '';

    public ?string $workerIdNumber = null;

    public ?string $workerInductionCompletedOn = null;

    public ?string $workerPoliceClearanceOn = null;

    public ?int $gateWorkerId = null;

    public ?string $gateResult = null;

    public ?string $gateReason = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('security.contractor.manage');
    }

    public function createContractor(): void
    {
        $this->validate(['companyName' => ['required', 'string']]);

        app(CreateContractorAction::class)->execute(new CreateContractorData(
            schoolId: $this->school->id,
            companyName: $this->companyName,
            contactPerson: $this->contactPerson,
            phone: $this->phone,
            workType: $this->workType,
        ));

        $this->reset(['companyName', 'contactPerson', 'phone', 'workType']);
        $this->toast(__('Contractor registered — pending approval.'));
    }

    public function selectForApproval(int $contractorId): void
    {
        $this->approvingContractorId = $contractorId;
    }

    public function approve(): void
    {
        if ($this->approvingContractorId === null) {
            return;
        }

        $this->validate([
            'insuranceExpiresOn' => ['required', 'date'],
            'safetyInductionOn' => ['required', 'date'],
            'inductionValidUntil' => ['required', 'date'],
        ]);

        app(ApproveContractorAction::class)->execute($this->approvingContractorId, new ApproveContractorData(
            insuranceExpiresOn: Carbon::parse($this->insuranceExpiresOn),
            safetyInductionOn: Carbon::parse($this->safetyInductionOn),
            inductionValidUntil: Carbon::parse($this->inductionValidUntil),
            approvedByUserId: (int) auth()->id(),
            policeClearanceOn: $this->policeClearanceOn !== null && $this->policeClearanceOn !== '' ? Carbon::parse($this->policeClearanceOn) : null,
        ));

        $this->reset(['approvingContractorId', 'insuranceExpiresOn', 'safetyInductionOn', 'inductionValidUntil', 'policeClearanceOn']);
        $this->toast(__('Contractor approved.'));
    }

    public function createWorker(): void
    {
        $this->validate([
            'workerContractorId' => ['required', 'integer'],
            'workerFullName' => ['required', 'string'],
        ]);

        app(CreateContractorWorkerAction::class)->execute(new CreateContractorWorkerData(
            schoolId: $this->school->id,
            contractorId: (int) $this->workerContractorId,
            fullName: $this->workerFullName,
            idNumber: $this->workerIdNumber,
            inductionCompletedOn: $this->workerInductionCompletedOn !== null && $this->workerInductionCompletedOn !== '' ? Carbon::parse($this->workerInductionCompletedOn) : null,
            policeClearanceOn: $this->workerPoliceClearanceOn !== null && $this->workerPoliceClearanceOn !== '' ? Carbon::parse($this->workerPoliceClearanceOn) : null,
        ));

        $this->reset(['workerFullName', 'workerIdNumber', 'workerInductionCompletedOn', 'workerPoliceClearanceOn']);
        $this->toast(__('Worker registered.'));
    }

    /**
     * No override parameter exists here, on purpose — a refusal from
     * `SignInContractorWorkerAction` is final from this screen.
     */
    public function signIn(): void
    {
        if ($this->gateWorkerId === null) {
            return;
        }

        $this->gateResult = null;
        $this->gateReason = null;

        try {
            app(SignInContractorWorkerAction::class)->execute($this->gateWorkerId, (int) auth()->id());
            $this->gateResult = 'ALLOWED';
        } catch (GateAccessRefusedException $e) {
            $this->gateResult = 'REFUSED';
            $this->gateReason = $e->getMessage();
        }
    }

    public function signOut(int $visitId): void
    {
        app(SignOutContractorWorkerAction::class)->execute($visitId, (int) auth()->id());
        $this->toast(__('Worker signed out.'));
    }

    public function render(): View
    {
        return view('security::contractors.index', [
            'contractors' => Contractor::where('school_id', $this->school->id)->orderBy('company_name')->get(),
            'workers' => ContractorWorker::with('contractor')->where('school_id', $this->school->id)->orderBy('full_name')->get(),
            'openVisits' => ContractorSiteVisit::with('contractorWorker')->where('school_id', $this->school->id)->whereNull('signed_out_at')->orderByDesc('signed_in_at')->get(),
        ]);
    }
}
