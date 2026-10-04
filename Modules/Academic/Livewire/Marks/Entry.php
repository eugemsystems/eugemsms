<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Marks;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\EnterMarkAction;
use Modules\Academic\Domain\Actions\PublishAssessmentAction;
use Modules\Academic\Domain\Actions\SubmitAssessmentMarksAction;
use Modules\Academic\Domain\DataObjects\EnterMarkData;
use Modules\Academic\Domain\DataObjects\PublishAssessmentData;
use Modules\Academic\Domain\DataObjects\SubmitAssessmentMarksData;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Marks\Entry` (Book D ACA-05 §5/§6 ⭐, `academic.result.enter`).
 * Hosts the whole draft-entry → submit → publish action bar for ONE
 * assessment (the same "one screen, whole lifecycle" shape as
 * `Admissions\Applications\Show`) rather than the spec's three
 * separate Entry/Submission/(part of) Review screens — folded because
 * each transition here is a single click with no surface of its own.
 * A plain HTML grid with a save-all button, not the spec's own
 * keyboard-navigable/Excel-paste grid — a real UX investment the
 * backend doesn't require (see `.ai/rules/academic.md`).
 */
#[Title('Mark entry')]
#[Layout('layouts.app')]
final class Entry extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Assessment $assessment;

    /** @var array<int, string> */
    public array $marks = [];

    /** @var array<int, bool> */
    public array $absentFlags = [];

    public function mount(School $school, Assessment $assessment): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.result.enter');

        abort_unless($assessment->school_id === $school->id, 404);

        $this->assessment = $assessment;

        foreach (AssessmentMark::where('assessment_id', $assessment->id)->get() as $mark) {
            $this->marks[$mark->student_id] = $mark->raw_mark !== null ? (string) $mark->raw_mark : '';
            $this->absentFlags[$mark->student_id] = $mark->is_absent;
        }
    }

    public function saveAll(): void
    {
        foreach ($this->roster() as $student) {
            $isAbsent = $this->absentFlags[$student->id] ?? false;
            $rawMark = $this->marks[$student->id] ?? '';

            if (! $isAbsent && $rawMark === '') {
                continue;
            }

            try {
                app(EnterMarkAction::class)->execute(new EnterMarkData(
                    assessmentId: $this->assessment->id,
                    studentId: $student->id,
                    enteredByUserId: (int) Auth::id(),
                    rawMark: ! $isAbsent ? (float) $rawMark : null,
                    isAbsent: $isAbsent,
                ));
            } catch (DomainException $e) {
                $this->toast("{$student->first_name} {$student->last_name}: {$e->getMessage()}", 'danger');

                return;
            }
        }

        $this->toast(__('Marks saved.'));
    }

    public function submit(): void
    {
        try {
            app(SubmitAssessmentMarksAction::class)->execute(new SubmitAssessmentMarksData(
                assessmentId: $this->assessment->id,
                submittedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->assessment = $this->assessment->fresh();
        $this->toast(__('Marks submitted.'));
    }

    public function publish(): void
    {
        $this->authorizePermission('academic.result.compute');

        try {
            app(PublishAssessmentAction::class)->execute(new PublishAssessmentData(
                assessmentId: $this->assessment->id,
                publishedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->assessment = $this->assessment->fresh();
        $this->toast(__('Assessment published — marks now feed the aggregation pipeline.'));
    }

    /**
     * @return Collection<int, Student>
     */
    private function roster(): Collection
    {
        return LearnerSubjectEnrolment::query()
            ->where('subject_id', $this->assessment->subject_id)
            ->where('term_id', $this->assessment->term_id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->values();
    }

    public function render(): View
    {
        return view('academic::marks.entry', [
            'roster' => $this->roster(),
        ]);
    }
}
