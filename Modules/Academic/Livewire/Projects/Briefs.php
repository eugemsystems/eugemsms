<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Projects;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateProjectBriefAction;
use Modules\Academic\Domain\DataObjects\CreateProjectBriefData;
use Modules\Academic\Domain\DataObjects\ProjectMilestoneInput;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectRubric;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;

/**
 * `Projects\Briefs` (Book E ACA-06 §5/§7, `academic.projects.view` to
 * list, `academic.projects.manage` to create). Folds the spec's separate
 * "Brief library" and "Brief editor" screens into one, the same shape
 * `Curriculum\Frameworks` uses — `CreateProjectBriefAction` takes the
 * brief and its milestones together (mirroring `CreateProjectRubricAction`),
 * so there is nothing an editor screen would do differently from create.
 * `academic.projects.approve`/Issue live on their own `Approve` screen —
 * a draft brief here is not yet eligible for issue.
 */
#[Title('Project briefs')]
#[Layout('layouts.app')]
final class Briefs extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $instrumentId = null;

    public ?int $subjectId = null;

    public ?int $gradeLevelId = null;

    public ?int $rubricId = null;

    public string $title = '';

    public string $description = '';

    public string $heritageLink = '';

    public string $startsOn = '';

    public string $dueOn = '';

    public string $maxMark = '100';

    /** @var array<int, string> */
    public array $deliverables = [''];

    /** @var array<int, array{title: string, dueOn: string, weightPercent: string}> */
    public array $milestones = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.projects.view');
    }

    public function addMilestone(): void
    {
        $this->milestones[] = ['title' => '', 'dueOn' => '', 'weightPercent' => ''];
    }

    public function removeMilestone(int $index): void
    {
        unset($this->milestones[$index]);
        $this->milestones = array_values($this->milestones);
    }

    public function addDeliverable(): void
    {
        $this->deliverables[] = '';
    }

    public function create(): void
    {
        $this->authorizePermission('academic.projects.manage');

        $this->validate([
            'instrumentId' => ['required', 'integer'],
            'subjectId' => ['required', 'integer'],
            'gradeLevelId' => ['required', 'integer'],
            'rubricId' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string'],
            'startsOn' => ['required', 'date'],
            'dueOn' => ['required', 'date', 'after:startsOn'],
            'maxMark' => ['required', 'numeric', 'min:1'],
        ]);

        $yearId = SessionContext::yearId();

        if ($yearId === null) {
            $this->toast(__('No active academic year is set for this school.'), 'danger');

            return;
        }

        try {
            app(CreateProjectBriefAction::class)->execute(new CreateProjectBriefData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                instrumentId: $this->instrumentId,
                subjectId: $this->subjectId,
                gradeLevelId: $this->gradeLevelId,
                title: $this->title,
                description: $this->description,
                deliverables: array_values(array_filter($this->deliverables, fn (string $d): bool => $d !== '')),
                startsOn: Carbon::parse($this->startsOn),
                dueOn: Carbon::parse($this->dueOn),
                maxMark: (float) $this->maxMark,
                rubricId: $this->rubricId,
                createdBy: (int) Auth::id(),
                milestones: array_values(array_filter(array_map(
                    fn (array $m, int $index): ?ProjectMilestoneInput => $m['title'] !== '' ? new ProjectMilestoneInput(
                        sequence: $index + 1,
                        title: $m['title'],
                        dueOn: Carbon::parse($m['dueOn']),
                        weightPercent: (float) ($m['weightPercent'] ?: 0),
                    ) : null,
                    $this->milestones,
                    array_keys($this->milestones),
                ))),
                heritageLink: $this->heritageLink !== '' ? $this->heritageLink : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['title', 'description', 'heritageLink', 'startsOn', 'dueOn', 'deliverables', 'milestones']);
        $this->deliverables = [''];
        $this->toast(__('Project brief created as draft.'));
    }

    public function render(): View
    {
        return view('academic::projects.briefs', [
            'briefs' => ProjectBrief::where('school_id', $this->school->id)->with('subject', 'gradeLevel', 'instrument')->orderByDesc('id')->get(),
            'instruments' => AssessmentInstrument::where('school_id', $this->school->id)->where('is_readonly', false)->orderBy('name')->get(),
            'subjects' => Subject::where('school_id', $this->school->id)->where('requires_sbp', true)->orderBy('name')->get(),
            'gradeLevels' => GradeLevel::where('school_id', $this->school->id)->orderBy('ordinal')->get(),
            'rubrics' => ProjectRubric::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
