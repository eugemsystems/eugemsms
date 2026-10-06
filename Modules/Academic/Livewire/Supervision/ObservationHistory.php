<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Supervision;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\AddObservationTeacherCommentAction;
use Modules\Academic\Domain\DataObjects\AddObservationTeacherCommentData;
use Modules\Academic\Livewire\Concerns\ResolvesSupervisionReach;
use Modules\Academic\Models\LessonObservation;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Academic\Supervision\ObservationHistory` (Book K ACA-11 §4). A teacher sees
 * their own observation records and may comment on them but never change the
 * observer's scores (BR-ACA-11-005); an observer sees the records they wrote;
 * a head of department sees their department's; school-reach `supervision.view`
 * sees all. A follow-up is shown against the observation it follows so the
 * trajectory reads across the term (BR-ACA-11-006).
 */
#[Title('Observation history')]
#[Layout('layouts.app')]
final class ObservationHistory extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesSupervisionReach;
    use Toasts;

    public ?int $commentingId = null;

    public string $commentText = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        abort_unless($this->holds('supervision.plan') || $this->holds('supervision.view') || $this->holds('supervision.observe'), 403);
    }

    public function startComment(int $observationId): void
    {
        $observation = $this->ownObservation($observationId);

        $this->commentingId = $observation?->id;
        $this->commentText = (string) $observation?->teacher_comments;
    }

    public function saveComment(): void
    {
        $observation = $this->commentingId === null ? null : $this->ownObservation($this->commentingId);

        if ($observation === null) {
            abort(403);
        }

        try {
            app(AddObservationTeacherCommentAction::class)->execute(new AddObservationTeacherCommentData($observation->id, (int) $this->ownStaff()?->id, $this->commentText));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('commentText', $exception->getMessage());

            return;
        }

        $this->reset('commentingId', 'commentText');
        $this->toast(__('Comment saved.'));
    }

    private function ownObservation(int $observationId): ?LessonObservation
    {
        $own = $this->ownStaff();

        return $own === null ? null : LessonObservation::query()->where('observed_staff_id', $own->id)->find($observationId);
    }

    public function render(): View
    {
        $own = $this->ownStaff();
        $visible = $this->visibleStaffIds();

        $observations = LessonObservation::query()
            ->when($visible !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('observed_staff_id', $visible)->when($own !== null, fn ($o) => $o->orWhere('observer_staff_id', $own?->id))))
            ->orderBy('observed_staff_id')->orderBy('observed_at')->limit(300)->get();

        $staffIds = $observations->pluck('observed_staff_id')->merge($observations->pluck('observer_staff_id'))->unique();

        return view('academic::supervision.observation-history', [
            'grouped' => $observations->groupBy('observed_staff_id'),
            'staffNames' => Staff::query()->whereIn('id', $staffIds)->get()->mapWithKeys(fn (Staff $s): array => [$s->id => $s->fullName()]),
            'ownStaffId' => $own?->id,
        ]);
    }
}
