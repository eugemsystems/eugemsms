<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\EarlyWarning;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\RecalculateStaffWellbeingIndicatorAction;
use Modules\Intelligence\Domain\Support\StaffWellbeingVisibility;
use Modules\Intelligence\Livewire\Concerns\ResolvesEarlyWarningTerm;
use Modules\Intelligence\Models\StaffWellbeingIndicator;
use Modules\People\Models\Staff;

/**
 * `Intelligence\EarlyWarning\StaffWellbeing` (Book J INT-03 §5,
 * `staff.wellbeing.view`). A staff member's own indicator plus their
 * direct reports' — and nobody else's, whatever permissions the viewer
 * holds (BR-INT-03-010, see `StaffWellbeingVisibility`). Every query
 * and every recalculation is limited to that set, re-derived on each
 * call; the page is a prompt for a supportive conversation, not a
 * performance judgement.
 */
#[Title('Staff wellbeing')]
#[Layout('layouts.app')]
final class StaffWellbeing extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesEarlyWarningTerm;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('staff.wellbeing.view');
        $this->termId = $this->defaultTermId();
    }

    public function recalculate(int $staffId): void
    {
        $this->authorizePermission('staff.wellbeing.view');

        abort_unless(in_array($staffId, $this->visibleStaffIds(), true), 403);

        $term = $this->selectedTerm();

        if ($term === null) {
            $this->toast(__('Choose a term first.'), 'danger');

            return;
        }

        app(RecalculateStaffWellbeingIndicatorAction::class)->execute($this->school->id, $staffId, $term->id);

        $this->toast(__('Recalculated from the workload record.'));
    }

    /**
     * @return array<int, int>
     */
    private function visibleStaffIds(): array
    {
        $user = auth()->user();

        return $user === null ? [] : StaffWellbeingVisibility::visibleStaffIds($user, $this->school->id);
    }

    public function render(): View
    {
        $term = $this->selectedTerm();
        $staffIds = $this->visibleStaffIds();

        return view('intelligence::early-warning.staff-wellbeing', [
            'terms' => $this->termChoices(),
            'staff' => Staff::where('school_id', $this->school->id)->whereIn('id', $staffIds)->orderBy('last_name')->get(),
            'indicators' => $term === null ? collect() : StaffWellbeingIndicator::where('school_id', $this->school->id)
                ->where('term_id', $term->id)->whereIn('staff_id', $staffIds)->get()->keyBy('staff_id'),
            'ownStaffId' => Staff::where('school_id', $this->school->id)->where('user_id', auth()->id())->value('id'),
        ]);
    }
}
