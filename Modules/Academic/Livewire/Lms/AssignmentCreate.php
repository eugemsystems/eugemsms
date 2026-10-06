<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Lms;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateAssignmentAction;
use Modules\Academic\Domain\DataObjects\CreateAssignmentData;
use Modules\Academic\Livewire\Concerns\AuthorizesCourseSpace;
use Modules\Academic\Models\AssessmentType;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Academic\Lms\AssignmentCreate` (Book K ACA-08 §5, `lms.assignment.create`).
 * Creates a draft assignment in one course space. The late policy is one of
 * exactly three (block, accept with a per-day penalty, accept and flag —
 * BR-ACA-08-004); linking an assessment type creates the gradebook
 * assessment its marks will sync into (BR-ACA-08-008), so a max mark is then
 * required. Rubric attachment is not built in this pass.
 */
#[Title('New assignment')]
#[Layout('layouts.app')]
final class AssignmentCreate extends Component
{
    use AuthorizesCourseSpace;
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $spaceId;

    public string $title = '';

    public string $instructions = '';

    public string $opensAt = '';

    public string $dueAt = '';

    public string $latePolicy = 'block';

    public string $penalty = '';

    public string $maxMark = '';

    public ?int $assessmentTypeId = null;

    public string $submissionType = 'file';

    public bool $allowsResubmission = false;

    public function mount(School $school, int $space): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('lms.assignment.create');

        $this->spaceId = $this->authorizeSpace($space, 'lms.assignment.create')->id;
        $this->opensAt = now()->format('Y-m-d\TH:i');
    }

    public function create(): void
    {
        $this->authorizePermission('lms.assignment.create');
        $this->resetErrorBag();

        $space = $this->authorizeSpace($this->spaceId, 'lms.assignment.create');

        $this->validate([
            'title' => ['required', 'string', 'max:200'], 'instructions' => ['required', 'string', 'max:10000'],
            'opensAt' => ['required', 'date'], 'dueAt' => ['required', 'date'],
            'penalty' => ['nullable', 'numeric', 'between:0,100'], 'maxMark' => ['nullable', 'numeric', 'gt:0'],
        ]);

        try {
            app(CreateAssignmentAction::class)->execute(new CreateAssignmentData(
                courseSpaceId: $space->id, title: $this->title, instructions: $this->instructions,
                opensAt: Carbon::parse($this->opensAt), dueAt: Carbon::parse($this->dueAt), latePolicy: $this->latePolicy,
                submissionType: $this->submissionType, createdByUserId: (int) auth()->id(),
                maxMark: $this->maxMark === '' ? null : (float) $this->maxMark, assessmentTypeId: $this->assessmentTypeId,
                latePenaltyPercentPerDay: $this->penalty === '' ? null : (float) $this->penalty, allowsResubmission: $this->allowsResubmission,
            ));
        } catch (InvalidArgumentException|DomainException $exception) {
            $this->addError('title', $exception->getMessage());

            return;
        }

        $this->redirectRoute('academic.lms.space', ['school' => $this->school, 'space' => $space->id], navigate: true);
    }

    public function render(): View
    {
        return view('academic::lms.assignment-create', ['assessmentTypes' => AssessmentType::query()->orderBy('name')->get(['id', 'name'])]);
    }
}
