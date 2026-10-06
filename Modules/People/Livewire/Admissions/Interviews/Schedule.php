<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Admissions\Interviews;

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
use Modules\People\Domain\Actions\RecordInterviewOutcomeAction;
use Modules\People\Domain\Actions\ScheduleInterviewAction;
use Modules\People\Domain\DataObjects\RecordInterviewOutcomeData;
use Modules\People\Domain\DataObjects\ScheduleInterviewData;
use Modules\People\Models\Application;
use Modules\People\Models\Interview;

/**
 * `Admissions\Interviews\Schedule` (Book C PPL-02 §5, `people.admissions.interview_manage`).
 * Book an interview with a panel, then record each criterion score and a
 * recommendation. A candidate who did not attend has no scores.
 */
#[Title('Interviews')]
#[Layout('layouts.app')]
final class Schedule extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $applicationId = null;

    public string $scheduledAt = '';

    public string $venue = '';

    /** @var array<int, int|string> */
    public array $panel = [];

    public ?int $recordingId = null;

    public string $scoresText = '';

    public string $recommendation = 'accept';

    public string $notes = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.admissions.interview_manage');
        $this->scheduledAt = now()->addDay()->format('Y-m-d\TH:i');
    }

    public function schedule(): void
    {
        $this->authorizePermission('people.admissions.interview_manage');
        $this->resetErrorBag();

        try {
            app(ScheduleInterviewAction::class)->execute(new ScheduleInterviewData(
                $this->school->id, (int) $this->applicationId, Carbon::parse($this->scheduledAt), array_map('intval', $this->panel), $this->venue === '' ? null : $this->venue,
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('applicationId', $exception->getMessage());

            return;
        }

        $this->reset('applicationId', 'venue', 'panel');
        $this->toast(__('Interview scheduled.'));
    }

    public function startRecording(int $interviewId): void
    {
        $this->recordingId = Interview::query()->whereNull('completed_at')->whereKey($interviewId)->value('id');
        $this->reset('scoresText', 'notes');
    }

    public function record(bool $attended): void
    {
        $this->authorizePermission('people.admissions.interview_manage');
        $this->resetErrorBag();

        if ($this->recordingId === null) {
            return;
        }

        $scores = [];

        foreach (preg_split('/\R/', $this->scoresText) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            [$criterion, $score] = array_pad(array_map('trim', explode(':', $line, 2)), 2, '');
            $scores[$criterion] = (float) $score;
        }

        try {
            app(RecordInterviewOutcomeAction::class)->execute(new RecordInterviewOutcomeData($this->recordingId, $attended, $scores, $attended ? $this->recommendation : null, $this->notes === '' ? null : $this->notes));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('scoresText', $exception->getMessage());

            return;
        }

        $this->reset('recordingId', 'scoresText', 'notes');
        $this->toast(__('Outcome recorded.'));
    }

    public function render(): View
    {
        $interviews = Interview::query()->orderByRaw('completed_at is null desc')->orderBy('scheduled_at')->limit(60)->get();
        $school = $this->school;

        return view('people::admissions.interviews', [
            'interviews' => $interviews,
            'applications' => Application::query()->whereIn('id', $interviews->pluck('application_id'))->get()->keyBy('id'),
            'candidates' => Application::query()->whereIn('status', ['submitted', 'under_review', 'exam_completed', 'waitlisted'])->orderBy('last_name')->limit(200)->get(),
            'staff' => $school->users()->orderBy('name')->get(['users.id', 'users.name']),
        ]);
    }
}
