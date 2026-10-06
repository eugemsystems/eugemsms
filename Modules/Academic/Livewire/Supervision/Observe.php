<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Supervision;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateObservationRubricAction;
use Modules\Academic\Domain\Actions\RecordLessonObservationAction;
use Modules\Academic\Domain\DataObjects\CreateObservationRubricData;
use Modules\Academic\Domain\DataObjects\RecordLessonObservationData;
use Modules\Academic\Livewire\Concerns\ResolvesSupervisionReach;
use Modules\Academic\Models\LessonObservation;
use Modules\Academic\Models\ObservationRubric;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Academic\Supervision\Observe` (Book K ACA-11 §4, `supervision.observe`).
 * Rubric entry for a lesson observation, laid out for a phone held in the
 * classroom. The observer is always the signed-in user's own staff record; an
 * observation cannot be of oneself, must score every criterion with one of its
 * defined levels, and a follow-up must be of the same teacher and later than
 * the one it follows (BR-ACA-11-006). Rubrics are created by
 * `supervision.rubric.manage` and share the criterion/level shape of ACA-06's
 * project rubrics (BR-ACA-11-004).
 */
#[Title('Observe a lesson')]
#[Layout('layouts.app')]
final class Observe extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesSupervisionReach;
    use Toasts;

    public ?int $observedStaffId = null;

    public ?int $rubricId = null;

    public string $observedAt = '';

    public string $classObserved = '';

    public ?int $subjectId = null;

    /** @var array<int, string> criterion position => chosen level */
    public array $scores = [];

    public string $strengths = '';

    public string $areas = '';

    public string $rating = '';

    public ?int $followUpId = null;

    public string $rubricName = '';

    public string $rubricCriteria = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        abort_unless($this->holds('supervision.observe') || $this->holds('supervision.rubric.manage'), 403);
        $this->observedAt = now()->format('Y-m-d\TH:i');
    }

    public function updatedRubricId(): void
    {
        $this->scores = [];
    }

    public function createRubric(): void
    {
        $this->authorizePermission('supervision.rubric.manage');
        $this->resetErrorBag();

        $criteria = [];

        foreach (preg_split('/\R/', $this->rubricCriteria) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            [$name, $levels] = array_pad(explode(':', $line, 2), 2, '');
            $criteria[] = ['criterion' => trim($name), 'descriptor_levels' => array_values(array_filter(array_map('trim', explode(',', $levels)), fn (string $l): bool => $l !== ''))];
        }

        try {
            app(CreateObservationRubricAction::class)->execute(new CreateObservationRubricData($this->school->id, $this->rubricName, $criteria));
        } catch (InvalidArgumentException $exception) {
            $this->addError('rubricName', $exception->getMessage());

            return;
        }

        $this->reset('rubricName', 'rubricCriteria');
        $this->toast(__('Rubric created.'));
    }

    public function record(): void
    {
        $this->authorizePermission('supervision.observe');
        $this->resetErrorBag();

        $observer = $this->ownStaff();
        $rubric = $this->rubricId === null ? null : ObservationRubric::query()->find($this->rubricId);
        $termId = $this->currentTermId();

        if ($observer === null || $rubric === null || $termId === null || $this->observedStaffId === null) {
            $this->addError('observedStaffId', __('Choose who you observed and a rubric (you also need a staff record and a current term).'));

            return;
        }

        $scores = [];

        foreach (array_values($rubric->criteria) as $position => $criterion) {
            $scores[(string) $criterion['criterion']] = (string) ($this->scores[$position] ?? '');
        }

        try {
            app(RecordLessonObservationAction::class)->execute(new RecordLessonObservationData(
                schoolId: $this->school->id, termId: $termId, observedStaffId: $this->observedStaffId, observerStaffId: $observer->id,
                rubricId: $rubric->id, observedAt: Carbon::parse($this->observedAt), scores: $scores,
                classObserved: $this->nullable($this->classObserved), subjectId: $this->subjectId, strengthsNoted: $this->nullable($this->strengths),
                areasForDevelopment: $this->nullable($this->areas), overallRating: $this->nullable($this->rating), followUpObservationId: $this->followUpId,
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('observedStaffId', $exception->getMessage());

            return;
        }

        $this->reset('observedStaffId', 'classObserved', 'scores', 'strengths', 'areas', 'rating', 'followUpId');
        $this->toast(__('Observation recorded.'));
    }

    private function nullable(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }

    public function render(): View
    {
        $observer = $this->ownStaff();
        $rubric = $this->rubricId === null ? null : ObservationRubric::query()->find($this->rubricId);

        return view('academic::supervision.observe', [
            'canObserve' => $this->holds('supervision.observe'),
            'canManageRubrics' => $this->holds('supervision.rubric.manage'),
            'staff' => Staff::query()->when($observer !== null, fn ($q) => $q->where('id', '!=', $observer?->id))->orderBy('last_name')->limit(400)->get(),
            'rubrics' => ObservationRubric::query()->orderBy('name')->get(['id', 'name']),
            'rubric' => $rubric,
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'earlier' => $this->observedStaffId === null || $observer === null ? collect() : LessonObservation::query()
                ->where('observed_staff_id', $this->observedStaffId)->where('observer_staff_id', $observer->id)->orderByDesc('observed_at')->limit(10)->get(['id', 'observed_at', 'overall_rating']),
        ]);
    }
}
