<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Groups;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\AssignToTeachingGroupAction;
use Modules\Academic\Domain\DataObjects\AssignToTeachingGroupData;
use Modules\Academic\Models\TeachingGroup;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Groups\Allocate` (Book D ACA-02 §6/BR-ACA-02-012/013,
 * `academic.group.manage`). Moves a learner between sets for a
 * subject — a pick-and-submit form, not the spec's own drag
 * interaction (same simplification as `Allocation\Classes`).
 * `AssignToTeachingGroupAction`'s own "exactly one membership per
 * subject per term" rule means picking a new group for an
 * already-enrolled subject is a move, never an addition.
 */
#[Title('Set allocation')]
#[Layout('layouts.app')]
final class Allocate extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $studentId = null;

    public ?int $teachingGroupId = null;

    public bool $acknowledgeCapacityWarning = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.group.manage');
    }

    public function assign(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'teachingGroupId' => ['required', 'integer'],
        ]);

        try {
            app(AssignToTeachingGroupAction::class)->execute(new AssignToTeachingGroupData(
                studentId: (int) $this->studentId,
                teachingGroupId: (int) $this->teachingGroupId,
                effectiveFrom: Carbon::now(),
                acknowledgeCapacityWarning: $this->acknowledgeCapacityWarning,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['studentId', 'teachingGroupId', 'acknowledgeCapacityWarning']);
        $this->toast(__('Learner assigned to group.'));
    }

    public function render(): View
    {
        $termId = SessionContext::termId();

        return view('academic::groups.allocate', [
            'groups' => $termId !== null ? TeachingGroup::where('term_id', $termId)->with('subject')->orderBy('code')->get() : collect(),
            'students' => Student::where('school_id', $this->school->id)->whereNotIn('status', ['withdrawn', 'graduated', 'archived'])->orderBy('first_name')->get(),
            'memberships' => $termId !== null
                ? TeachingGroupMember::whereHas('teachingGroup', fn ($q) => $q->where('term_id', $termId))->whereNull('effective_to')->with('student', 'teachingGroup.subject')->orderByDesc('id')->limit(50)->get()
                : collect(),
        ]);
    }
}
