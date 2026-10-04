<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Selection;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\SubmitSubjectSelectionAction;
use Modules\Academic\Domain\DataObjects\SubmitSubjectSelectionData;
use Modules\Academic\Domain\Exceptions\SubjectSelectionRequiresAcknowledgementException;
use Modules\Academic\Domain\Support\SubjectSelectionRuleEngine;
use Modules\Academic\Models\Pathway;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\PreviewIndicativeFeeAction;
use Modules\Finance\Domain\DataObjects\PreviewIndicativeFeeData;
use Modules\People\Models\Student;

/**
 * `Selection\Form` (Book D ACA-02 §6/BR-ACA-02-015/016,
 * `academic.selection.submit`). The staff-facing stand-in for the
 * spec's learner/guardian-facing public form (no portal exists in
 * this pass) — live rule validation through the same
 * `SubjectSelectionRuleEngine` the Action itself calls, and the
 * indicative fee through FIN-02's own `PreviewIndicativeFeeAction`,
 * "the same engine that will later bill them" (BR-ACA-02-016).
 */
#[Title('Subject selection')]
#[Layout('layouts.app')]
final class Form extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public Student $student;

    public ?int $pathwayId = null;

    /** @var array<int, int> */
    public array $selectedSubjectIds = [];

    public bool $acknowledgeWarnings = false;

    /** @var array{isValid: bool, warnings: array<int, string>, blocks: array<int, string>}|null */
    public ?array $validationPreview = null;

    /** @var array{amountMinor: int|null, currency: string|null}|null */
    public ?array $feePreview = null;

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.selection.submit');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
        $this->pathwayId = Pathway::where('school_id', $school->id)->where('code', $student->pathway)->value('id');
    }

    public function checkSelection(): void
    {
        if ($this->selectedSubjectIds === []) {
            $this->validationPreview = null;
            $this->feePreview = null;

            return;
        }

        $subjects = collect($this->selectedSubjectIds)->map(fn ($id): int => (int) $id);
        $framework = Subject::withoutGlobalScopes()->find($subjects->first())?->framework_id;
        $pathwayCode = $this->pathwayId !== null ? Pathway::withoutGlobalScopes()->find($this->pathwayId)?->code : null;

        $result = app(SubjectSelectionRuleEngine::class)->validate(
            $subjects,
            $framework ?? 0,
            $this->student->grade_level_id,
            $pathwayCode,
            $this->school->id,
            SessionContext::yearId(),
            $this->student->id,
        );

        $this->validationPreview = [
            'isValid' => $result->isValid,
            'warnings' => $result->warnings->pluck('message')->all(),
            'blocks' => $result->blocks->pluck('message')->all(),
        ];

        $termId = SessionContext::termId();

        if ($termId !== null) {
            $preview = app(PreviewIndicativeFeeAction::class)->execute(new PreviewIndicativeFeeData(
                studentId: $this->student->id,
                termId: $termId,
                subjectIds: $subjects->all(),
            ));

            $this->feePreview = ['amountMinor' => $preview->amountMinor, 'currency' => $preview->currency];
        }
    }

    public function submit(): void
    {
        $this->validate(['selectedSubjectIds' => ['required', 'array', 'min:1']]);

        $yearId = SessionContext::yearId();

        if ($yearId === null) {
            $this->addError('selectedSubjectIds', __('No active academic year is set for this school.'));

            return;
        }

        try {
            app(SubmitSubjectSelectionAction::class)->execute(new SubmitSubjectSelectionData(
                studentId: $this->student->id,
                academicYearId: $yearId,
                gradeLevelId: (int) $this->student->grade_level_id,
                selectedSubjectIds: array_map('intval', $this->selectedSubjectIds),
                submittedByUserId: (int) Auth::id(),
                pathwayId: $this->pathwayId,
                indicativeFeeMinor: $this->feePreview['amountMinor'] ?? null,
                indicativeFeeCurrency: $this->feePreview['currency'] ?? null,
                acknowledgeWarnings: $this->acknowledgeWarnings,
            ));
        } catch (SubjectSelectionRequiresAcknowledgementException $e) {
            $this->toast($e->getMessage(), 'warning');

            return;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->redirectRoute('academic.selection.approvals', ['school' => $this->school], navigate: true);
    }

    public function render(): View
    {
        return view('academic::selection.form', [
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
