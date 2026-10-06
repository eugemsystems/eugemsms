<?php

declare(strict_types=1);

namespace Modules\Operations\Livewire\Projects;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Operations\Domain\Actions\AddCapitalProjectMilestoneAction;
use Modules\Operations\Domain\Actions\AdvanceCapitalProjectStatusAction;
use Modules\Operations\Domain\Actions\AssignCapitalProjectContractorAction;
use Modules\Operations\Domain\Actions\CompleteCapitalProjectAction;
use Modules\Operations\Domain\Actions\CompleteCapitalProjectMilestoneAction;
use Modules\Operations\Domain\Actions\CreateCapitalProjectAction;
use Modules\Operations\Domain\DataObjects\CreateCapitalProjectData;
use Modules\Operations\Models\CapitalProject;
use Modules\Operations\Models\CapitalProjectMilestone;
use Modules\Stores\Models\BudgetLine;
use Modules\Stores\Models\Supplier;

/**
 * `Projects\Index` (Book H2 OPS-02 §7/BR-OPS-02-015,
 * `maintenance.project.manage`). Create, advance
 * (`AdvanceCapitalProjectStatusAction` — this pass's own gap-fill, see
 * its docblock) and complete, one action bar per project. "Details" opens a
 * panel to name the main contractor and to add and complete milestones
 * (`AddCapitalProjectMilestoneAction`, `CompleteCapitalProjectMilestoneAction`,
 * `AssignCapitalProjectContractorAction`).
 */
#[Title('Capital projects')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $name = '';

    public string $description = '';

    public string $budgetMinor = '';

    public ?int $budgetLineId = null;

    public string $startsOn = '';

    public bool $capitaliseOnCompletion = true;

    public ?int $selectedProjectId = null;

    public string $milestoneName = '';

    public string $milestoneTargetDate = '';

    public string $milestonePaymentPercent = '';

    public ?int $contractorId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('maintenance.project.manage');
        $this->startsOn = now()->toDateString();
    }

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:200'],
            'budgetMinor' => ['required', 'integer', 'gt:0'],
            'startsOn' => ['required', 'date'],
        ]);

        app(CreateCapitalProjectAction::class)->execute(new CreateCapitalProjectData(
            schoolId: $this->school->id,
            name: $this->name,
            description: $this->description !== '' ? $this->description : null,
            budgetMinor: (int) $this->budgetMinor,
            currency: $this->school->base_currency,
            startsOn: Carbon::parse($this->startsOn),
            createdByUserId: (int) auth()->id(),
            budgetLineId: $this->budgetLineId,
            capitaliseOnCompletion: $this->capitaliseOnCompletion,
        ));

        $this->reset(['name', 'description', 'budgetMinor', 'budgetLineId']);
        $this->toast(__('Capital project created.'));
    }

    public function select(int $projectId): void
    {
        $project = CapitalProject::query()->where('school_id', $this->school->id)->find($projectId);

        $this->selectedProjectId = $project?->id;
        $this->contractorId = $project?->main_contractor_id;
        $this->milestoneTargetDate = now()->addMonth()->toDateString();
        $this->resetErrorBag();
    }

    public function assignContractor(): void
    {
        $this->authorizePermission('maintenance.project.manage');

        if ($this->selectedProjectId === null || $this->contractorId === null) {
            return;
        }

        try {
            app(AssignCapitalProjectContractorAction::class)->execute($this->selectedProjectId, $this->contractorId);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Contractor assigned.'));
    }

    public function addMilestone(): void
    {
        $this->authorizePermission('maintenance.project.manage');
        $this->resetErrorBag();
        $this->validate([
            'milestoneName' => ['required', 'string', 'max:150'],
            'milestoneTargetDate' => ['required', 'date'],
            'milestonePaymentPercent' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        if ($this->selectedProjectId === null) {
            return;
        }

        try {
            app(AddCapitalProjectMilestoneAction::class)->execute(
                $this->selectedProjectId,
                $this->milestoneName,
                Carbon::parse($this->milestoneTargetDate),
                $this->milestonePaymentPercent !== '' ? (float) $this->milestonePaymentPercent : null,
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset('milestoneName', 'milestonePaymentPercent');
        $this->toast(__('Milestone added.'));
    }

    public function completeMilestone(int $milestoneId): void
    {
        $this->authorizePermission('maintenance.project.manage');

        $milestone = CapitalProjectMilestone::query()->where('project_id', $this->selectedProjectId)->find($milestoneId);

        if ($milestone === null) {
            return;
        }

        try {
            app(CompleteCapitalProjectMilestoneAction::class)->execute($milestone->id, now());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Milestone completed.'));
    }

    public function advance(int $projectId): void
    {
        try {
            app(AdvanceCapitalProjectStatusAction::class)->execute($projectId);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Project advanced.'));
    }

    public function complete(int $projectId): void
    {
        try {
            app(CompleteCapitalProjectAction::class)->execute(
                $projectId,
                (int) SessionContext::yearId(),
                (int) SessionContext::termId(),
                (int) auth()->id(),
            );
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Project completed.'));
    }

    public function render(): View
    {
        return view('operations::projects.index', [
            'projects' => CapitalProject::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'selected' => $this->selectedProjectId !== null ? CapitalProject::query()->where('school_id', $this->school->id)->with(['milestones' => fn ($q) => $q->orderBy('sequence'), 'mainContractor:id,name'])->find($this->selectedProjectId) : null,
            'contractors' => Supplier::query()->where('school_id', $this->school->id)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'budgetLines' => BudgetLine::where('school_id', $this->school->id)->orderBy('id')->limit(200)->get(),
        ]);
    }
}
