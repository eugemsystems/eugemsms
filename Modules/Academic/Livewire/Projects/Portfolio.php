<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Projects;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CompileProjectPortfolioAction;
use Modules\Academic\Domain\DataObjects\CompileProjectPortfolioData;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectPortfolio;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Projects\Portfolio` (Book E ACA-06 §7/§9, `academic.projects.view`,
 * BR-ACA-06-017). Compiles the brief, every milestone, all evidence,
 * the rubric breakdown, and the marker/moderator comments into one
 * document — generated from source every time, like `Results\Transcripts`.
 */
#[Title('Project portfolio')]
#[Layout('layouts.app')]
final class Portfolio extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public LearnerProject $learnerProject;

    public function mount(School $school, LearnerProject $learnerProject): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.projects.view');

        abort_unless($learnerProject->school_id === $school->id, 404);

        $this->learnerProject = $learnerProject;
    }

    public function compile(): void
    {
        $this->authorizePermission('academic.projects.view');

        try {
            app(CompileProjectPortfolioAction::class)->execute(new CompileProjectPortfolioData(
                learnerProjectId: $this->learnerProject->id,
                compiledByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Portfolio compiled.'));
    }

    public function render(): View
    {
        return view('academic::projects.portfolio', [
            'portfolios' => ProjectPortfolio::where('learner_project_id', $this->learnerProject->id)->with('document')->latest('id')->get(),
        ]);
    }
}
