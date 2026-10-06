<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Admissions\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\ProcessEntranceExamResultsAction;
use Modules\People\Domain\Actions\PublishEntranceExamResultsAction;
use Modules\People\Domain\Actions\RecordExamMarksAction;
use Modules\People\Domain\Actions\RegisterExamCandidateAction;
use Modules\People\Domain\Actions\ScheduleEntranceExamAction;
use Modules\People\Domain\DataObjects\RecordExamMarksData;
use Modules\People\Domain\DataObjects\RegisterExamCandidateData;
use Modules\People\Domain\DataObjects\ScheduleEntranceExamData;
use Modules\People\Models\Application;
use Modules\People\Models\EntranceExam;
use Modules\People\Models\EntranceExamCandidate;
use Modules\People\Models\Intake;

/**
 * `Admissions\Exams\Manage` (Book C PPL-02 §5, `people.admissions.exam_manage`).
 * Schedule an entrance exam, seat applicants, capture marks per paper and
 * publish the ranked results. Mark entry refuses a mark over its paper's
 * maximum; ranking waits until every candidate is present or absent.
 */
#[Title('Entrance exams')]
#[Layout('layouts.app')]
final class Manage extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $intakeId = null;

    public string $name = '';

    public string $examDate = '';

    public string $startTime = '09:00';

    public string $venue = '';

    public string $capacity = '';

    public string $papersText = '';

    public ?int $examId = null;

    public ?int $applicationId = null;

    /** @var array<int, array<string, mixed>> candidateId => paper => mark (client-supplied, validated server-side) */
    public array $marks = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.admissions.exam_manage');
        $this->examDate = now()->addWeek()->toDateString();
    }

    public function schedule(): void
    {
        $this->authorizePermission('people.admissions.exam_manage');
        $this->resetErrorBag();

        $papers = [];

        foreach (preg_split('/\R/', $this->papersText) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            [$subject, $max, $weight] = array_pad(array_map('trim', explode('|', $line)), 3, '');
            $papers[] = ['subject' => $subject, 'max_mark' => (float) $max, 'weight' => (float) $weight];
        }

        try {
            $exam = app(ScheduleEntranceExamAction::class)->execute(new ScheduleEntranceExamData(
                schoolId: $this->school->id, intakeId: (int) $this->intakeId, name: $this->name, examDate: Carbon::parse($this->examDate), startTime: $this->startTime, papers: $papers,
                venue: $this->venue === '' ? null : $this->venue, capacity: $this->capacity === '' ? null : (int) $this->capacity,
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('name', $exception->getMessage());

            return;
        }

        $this->reset('name', 'papersText', 'venue', 'capacity');
        $this->examId = $exam->id;
        $this->toast(__('Exam scheduled.'));
    }

    public function open(int $id): void
    {
        $this->examId = EntranceExam::query()->whereKey($id)->value('id');
        $this->marks = [];
    }

    public function seat(): void
    {
        $this->authorizePermission('people.admissions.exam_manage');
        $this->resetErrorBag();

        if ($this->examId === null || $this->applicationId === null) {
            return;
        }

        try {
            app(RegisterExamCandidateAction::class)->execute(new RegisterExamCandidateData($this->examId, $this->applicationId));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('applicationId', $exception->getMessage());

            return;
        }

        $this->reset('applicationId');
        $this->toast(__('Seated.'));
    }

    public function saveMarks(int $candidateId, bool $attended): void
    {
        $this->authorizePermission('people.admissions.exam_manage');
        $this->resetErrorBag();

        $candidate = EntranceExamCandidate::query()->where('exam_id', $this->examId)->findOrFail($candidateId);
        $marks = collect($this->marks[$candidateId] ?? [])->filter(fn ($v): bool => is_numeric($v))->map(fn ($v): float => (float) $v)->all();

        try {
            app(RecordExamMarksAction::class)->execute(new RecordExamMarksData($candidate->id, $attended, $attended ? $marks : []));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('marks.'.$candidateId, $exception->getMessage());

            return;
        }

        $this->toast($attended ? __('Marks saved.') : __('Marked absent.'));
    }

    public function process(): void
    {
        $this->authorizePermission('people.admissions.exam_manage');
        $this->run(fn () => app(ProcessEntranceExamResultsAction::class)->execute((int) $this->examId), __('Results ranked.'));
    }

    public function publish(): void
    {
        $this->authorizePermission('people.admissions.exam_manage');
        $this->run(fn () => app(PublishEntranceExamResultsAction::class)->execute((int) $this->examId), __('Results published.'));
    }

    private function run(callable $callback, string $success): void
    {
        try {
            $callback();
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast($success);
    }

    public function render(): View
    {
        $exam = $this->examId === null ? null : EntranceExam::query()->find($this->examId);
        $candidates = $exam === null ? collect() : EntranceExamCandidate::query()->where('exam_id', $exam->id)->orderBy('rank_in_exam')->orderBy('candidate_number')->get();
        $applications = Application::query()->whereIn('id', $candidates->pluck('application_id'))->get()->keyBy('id');

        return view('people::admissions.exams', [
            'intakes' => Intake::query()->orderByDesc('id')->get(['id', 'name']),
            'exams' => EntranceExam::query()->orderByDesc('exam_date')->limit(30)->get(),
            'exam' => $exam,
            'candidates' => $candidates,
            'applications' => $applications,
            'eligible' => $exam === null ? collect() : Application::query()->where('intake_id', $exam->intake_id)->whereIn('status', ['submitted', 'under_review', 'exam_completed', 'interview_completed', 'waitlisted'])->whereNotIn('id', $candidates->pluck('application_id'))->orderBy('last_name')->limit(100)->get(),
        ]);
    }
}
