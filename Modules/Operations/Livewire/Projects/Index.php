<?php

declare(strict_types=1);

namespace Modules\Operations\Livewire\Projects;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Operations\Domain\Actions\AdvanceCapitalProjectStatusAction;
use Modules\Operations\Domain\Actions\CompleteCapitalProjectAction;
use Modules\Operations\Domain\Actions\CreateCapitalProjectAction;
use Modules\Operations\Domain\DataObjects\CreateCapitalProjectData;
use Modules\Operations\Models\CapitalProject;
use Modules\Stores\Models\BudgetLine;

/**
 * `Projects\Index` (Book H2 OPS-02 §7/BR-OPS-02-015,
 * `maintenance.project.manage`). Create, advance
 * (`AdvanceCapitalProjectStatusAction` — this pass's own gap-fill, see
 * its docblock) and complete, one action bar per project. Milestones
 * (`capital_project_milestones`) are a deliberately NOT built part of
 * this screen — no Action anywhere in the shipped domain layer ever
 * creates one (verified: model/migration/factory exist, only
 * `OperationsServiceProvider`'s own tenancy-isolation-test factory
 * call creates a row), and no acceptance criterion in this book names
 * a milestone-level behaviour to build a UI against.
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
            'budgetLines' => BudgetLine::where('school_id', $this->school->id)->orderBy('id')->limit(200)->get(),
        ]);
    }
}
