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
use Modules\Academic\Domain\Actions\ComputeCoverageStatusAction;
use Modules\Academic\Domain\Actions\RecordTopicDeliveryAction;
use Modules\Academic\Domain\DataObjects\ComputeCoverageStatusData;
use Modules\Academic\Domain\DataObjects\RecordTopicDeliveryData;
use Modules\Academic\Livewire\Concerns\ResolvesSupervisionReach;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * `Academic\Supervision\Coverage` (Book K ACA-11 §4/BR-ACA-11-002). A traffic
 * light per teacher per subject: a topic delivered after its planned week, or
 * not yet delivered once that week has passed, shows as behind — a fact for
 * the HOD to act on, never a verdict. A teacher records delivery against their
 * own scheme; holders of `supervision.scheme.approve` may correct any.
 */
#[Title('Coverage tracker')]
#[Layout('layouts.app')]
final class Coverage extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesSupervisionReach;
    use Toasts;

    public ?int $termId = null;

    /** @var array<string, string> "schemeId:topicIndex" => date */
    public array $dates = [];

    /** @var array<string, string> "schemeId:topicIndex" => note */
    public array $notes = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        abort_unless($this->holds('supervision.plan') || $this->holds('supervision.view'), 403);
        $this->termId = $this->currentTermId();
    }

    public function record(int $schemeId, int $topicIndex): void
    {
        $scheme = SchemeOfWork::query()->find($schemeId);

        if ($scheme === null || ! $this->mayRecord($scheme)) {
            abort(403);
        }

        $key = "{$schemeId}:{$topicIndex}";
        $date = $this->dates[$key] ?? '';

        if ($date === '' || strtotime($date) === false) {
            $this->toast(__('Enter the date the topic was taught.'), 'danger');

            return;
        }

        try {
            app(RecordTopicDeliveryAction::class)->execute(new RecordTopicDeliveryData($scheme->id, $topicIndex, Carbon::parse($date), ($this->notes[$key] ?? '') === '' ? null : $this->notes[$key]));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        unset($this->dates[$key], $this->notes[$key]);
        $this->toast(__('Recorded.'));
    }

    private function mayRecord(SchemeOfWork $scheme): bool
    {
        return $this->holds('supervision.scheme.approve') || ($this->holds('supervision.plan') && $this->ownStaff()?->id === $scheme->teacher_staff_id);
    }

    public function render(): View
    {
        $visible = $this->visibleStaffIds();

        $schemes = SchemeOfWork::query()->where('term_id', $this->termId)
            ->when($visible !== null, fn ($q) => $q->whereIn('teacher_staff_id', $visible))
            ->orderBy('teacher_staff_id')->limit(200)->get();

        $coverage = [];

        foreach ($schemes as $scheme) {
            $coverage[$scheme->id] = app(ComputeCoverageStatusAction::class)->execute(new ComputeCoverageStatusData($scheme->id));
        }

        return view('academic::supervision.coverage', [
            'terms' => Term::query()->orderByDesc('starts_on')->limit(8)->get(['id', 'name']),
            'schemes' => $schemes,
            'coverage' => $coverage,
            'subjectNames' => Subject::query()->pluck('name', 'id'),
            'gradeNames' => GradeLevel::query()->pluck('name', 'id'),
            'teacherNames' => Staff::query()->whereIn('id', $schemes->pluck('teacher_staff_id'))->get()->mapWithKeys(fn (Staff $s): array => [$s->id => $s->fullName()]),
            'recordable' => $schemes->filter(fn (SchemeOfWork $s): bool => $this->mayRecord($s))->pluck('id')->all(),
        ]);
    }
}
