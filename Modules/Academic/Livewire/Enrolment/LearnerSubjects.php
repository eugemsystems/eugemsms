<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Enrolment;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\DropSubjectAction;
use Modules\Academic\Domain\Actions\EnrolSubjectAction;
use Modules\Academic\Domain\DataObjects\DropSubjectData;
use Modules\Academic\Domain\DataObjects\EnrolSubjectData;
use Modules\Academic\Domain\Exceptions\SubjectSelectionRequiresAcknowledgementException;
use Modules\Academic\Domain\Support\SubjectEnrolmentQuery;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\PreviewIndicativeFeeAction;
use Modules\Finance\Domain\DataObjects\PreviewIndicativeFeeData;
use Modules\People\Models\Student;

/**
 * `Enrolment\LearnerSubjects` (Book D ACA-02 §4/§6 ⭐,
 * `academic.enrolment.manage` to add/drop, `.view` to open). The
 * screen the whole part-time billing model depends on: current
 * subjects, add/drop with an effective date, the full dated history
 * from `subject_enrolment_changes`, and a live fee impact preview
 * through FIN-02's own `PreviewIndicativeFeeAction` — "the same engine
 * that will later bill them" (§4), never a second calculation.
 *
 * A `warn`-severity rule violation surfaces
 * `SubjectSelectionRequiresAcknowledgementException`'s own message and
 * an "acknowledge and retry" checkbox rather than silently retrying —
 * the acknowledging user must be a deliberate click, per BR-ACA-01-008.
 */
#[Title('Subject enrolment')]
#[Layout('layouts.app')]
final class LearnerSubjects extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public Student $student;

    public ?int $addSubjectId = null;

    public string $addEffectiveFrom = '';

    public string $addReason = '';

    public bool $acknowledgeWarnings = false;

    public ?string $pendingWarningMessage = null;

    public ?int $dropSubjectId = null;

    public string $dropEffectiveTo = '';

    public string $dropReason = '';

    /** @var array{amountMinor: int|null, currency: string|null}|null */
    public ?array $feePreview = null;

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.enrolment.view');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
        $this->addEffectiveFrom = now()->toDateString();
        $this->dropEffectiveTo = now()->toDateString();
    }

    public function previewFeeImpact(): void
    {
        if ($this->addSubjectId === null) {
            return;
        }

        $termId = SessionContext::termId();

        if ($termId === null) {
            return;
        }

        $currentSubjectIds = LearnerSubjectEnrolment::query()
            ->where('student_id', $this->student->id)
            ->where('term_id', $termId)
            ->where('status', 'active')
            ->pluck('subject_id')
            ->push($this->addSubjectId)
            ->unique()
            ->values()
            ->all();

        $preview = app(PreviewIndicativeFeeAction::class)->execute(new PreviewIndicativeFeeData(
            studentId: $this->student->id,
            termId: $termId,
            subjectIds: $currentSubjectIds,
        ));

        $this->feePreview = ['amountMinor' => $preview->amountMinor, 'currency' => $preview->currency];
    }

    public function addSubject(): void
    {
        $this->authorizePermission('academic.enrolment.manage');

        $this->validate([
            'addSubjectId' => ['required', 'integer'],
            'addEffectiveFrom' => ['required', 'date'],
        ]);

        $termId = SessionContext::termId();

        if ($termId === null) {
            $this->addError('addSubjectId', __('No active term is set for this school.'));

            return;
        }

        try {
            app(EnrolSubjectAction::class)->execute(new EnrolSubjectData(
                studentId: $this->student->id,
                subjectId: (int) $this->addSubjectId,
                termId: $termId,
                addedByUserId: (int) Auth::id(),
                enrolmentReason: 'elective',
                effectiveFrom: Carbon::parse($this->addEffectiveFrom),
                acknowledgeWarnings: $this->acknowledgeWarnings,
                reason: $this->addReason !== '' ? $this->addReason : null,
            ));
        } catch (SubjectSelectionRequiresAcknowledgementException $e) {
            $this->pendingWarningMessage = $e->getMessage();

            return;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['addSubjectId', 'addReason', 'acknowledgeWarnings', 'pendingWarningMessage', 'feePreview']);
        $this->toast(__('Subject added.'));
    }

    public function dropSubject(): void
    {
        $this->authorizePermission('academic.enrolment.manage');

        $this->validate([
            'dropSubjectId' => ['required', 'integer'],
            'dropEffectiveTo' => ['required', 'date'],
            'dropReason' => ['required', 'string', 'max:255'],
        ]);

        $termId = SessionContext::termId();

        if ($termId === null) {
            $this->addError('dropSubjectId', __('No active term is set for this school.'));

            return;
        }

        try {
            app(DropSubjectAction::class)->execute(new DropSubjectData(
                studentId: $this->student->id,
                subjectId: (int) $this->dropSubjectId,
                termId: $termId,
                droppedByUserId: (int) Auth::id(),
                effectiveTo: Carbon::parse($this->dropEffectiveTo),
                dropReason: $this->dropReason,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['dropSubjectId', 'dropReason']);
        $this->toast(__('Subject dropped.'));
    }

    public function render(): View
    {
        $termId = SessionContext::termId();
        $term = $termId !== null ? Term::find($termId) : null;

        $activeEnrolments = $termId !== null
            ? LearnerSubjectEnrolment::where('student_id', $this->student->id)->where('term_id', $termId)->where('status', 'active')->with('subject')->get()
            : collect();

        return view('academic::enrolment.learner-subjects', [
            'activeEnrolments' => $activeEnrolments,
            'history' => $term !== null ? app(SubjectEnrolmentQuery::class)->changesInTerm($this->student, $term) : collect(),
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
