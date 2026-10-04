<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Timetable;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\AssignSubstituteCoverAction;
use Modules\Academic\Domain\Actions\SuggestCoverAction;
use Modules\Academic\Domain\DataObjects\AssignSubstituteCoverData;
use Modules\Academic\Domain\DataObjects\SuggestCoverData;
use Modules\Academic\Models\LessonSubstitution;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Timetable\Cover` (Book E ACA-03 §6/BR-ACA-03-017/018/019,
 * `academic.timetable.cover_manage`). Today's pending substitutions,
 * `SuggestCoverAction`'s ranked candidates, and one-click assign —
 * uncovered lessons are highlighted per the spec's own screen
 * description. The "same department" tier and the deputy-head fallback
 * `SuggestCoverAction` itself does not automate are simply absent from
 * the suggestion list; the dropdown still lets staff pick any teacher
 * manually.
 */
#[Title('Daily cover')]
#[Layout('layouts.app')]
final class Cover extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<int, array<int, int>> */
    public array $suggestions = [];

    /** @var array<int, int|null> */
    public array $selectedCover = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.timetable.cover_manage');
    }

    public function suggest(int $substitutionId): void
    {
        $this->suggestions[$substitutionId] = app(SuggestCoverAction::class)
            ->execute(new SuggestCoverData(substitutionId: $substitutionId))
            ->take(5)
            ->all();
    }

    public function assign(int $substitutionId): void
    {
        $coverStaffId = $this->selectedCover[$substitutionId] ?? null;

        if ($coverStaffId === null) {
            $this->toast(__('Select a covering teacher first.'), 'danger');

            return;
        }

        try {
            app(AssignSubstituteCoverAction::class)->execute(new AssignSubstituteCoverData(
                substitutionId: $substitutionId,
                coverStaffId: $coverStaffId,
                assignedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        unset($this->selectedCover[$substitutionId], $this->suggestions[$substitutionId]);
        $this->toast(__('Cover assigned.'));
    }

    public function render(): View
    {
        $substitutions = LessonSubstitution::where('school_id', $this->school->id)
            ->whereDate('substitution_date', now()->toDateString())
            ->with('timetableSlot', 'absentStaff', 'coverStaff')
            ->orderBy('status')
            ->get();

        $staffNames = Staff::where('school_id', $this->school->id)->get()->mapWithKeys(
            fn (Staff $s): array => [$s->id => "{$s->first_name} {$s->last_name}"]
        );

        return view('academic::timetable.cover', [
            'substitutions' => $substitutions,
            'staffNames' => $staffNames,
        ]);
    }
}
