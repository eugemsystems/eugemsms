<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Allocation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CreateLearnerIncompatibilityAction;
use Modules\Boarding\Domain\DataObjects\CreateLearnerIncompatibilityData;
use Modules\Boarding\Models\LearnerIncompatibility;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Allocation\Incompatibilities` (Book F BRD-01 §5,
 * `boarding.incompatibility.manage` ⚠). List + create, via the new
 * gap-filling `CreateLearnerIncompatibilityAction`. BR-BRD-01-008's
 * confidentiality is enforced here, not just in the Action: `reason`
 * is never rendered for a row flagged `is_confidential` — the view
 * only ever receives `'[restricted]'` for that field, so there is no
 * client-side value to leak, matching this codebase's "absent, not
 * merely hidden" rule for medical/safeguarding fields elsewhere
 * (CLAUDE.md's tiered-visibility rule).
 */
#[Title('Learner incompatibilities')]
#[Layout('layouts.app')]
final class Incompatibilities extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentAId = null;

    public ?int $studentBId = null;

    public string $scope = 'room';

    public string $reasonCategory = 'conflict';

    public string $reason = '';

    public bool $isConfidential = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.incompatibility.manage');
    }

    public function create(): void
    {
        $this->validate([
            'studentAId' => ['required', 'integer', 'different:studentBId'],
            'studentBId' => ['required', 'integer'],
            'scope' => ['required', 'in:room,wing,hostel'],
            'reasonCategory' => ['required', 'in:bullying,conflict,safeguarding,family_request,medical'],
        ]);

        app(CreateLearnerIncompatibilityAction::class)->execute(new CreateLearnerIncompatibilityData(
            schoolId: $this->school->id,
            studentAId: (int) $this->studentAId,
            studentBId: (int) $this->studentBId,
            scope: $this->scope,
            reasonCategory: $this->reasonCategory,
            raisedByUserId: (int) Auth::id(),
            reason: $this->reason !== '' ? $this->reason : null,
            isConfidential: $this->isConfidential,
        ));

        $this->reset(['studentAId', 'studentBId', 'reason']);
        $this->toast(__('Incompatibility recorded — the allocation engine will honour it.'));
    }

    public function render(): View
    {
        $canViewReason = app(PermissionScopeResolver::class)
            ->has(Auth::user(), 'boarding.incompatibility.manage', PermissionScope::Own, $this->school->id);

        $incompatibilities = LearnerIncompatibility::where('school_id', $this->school->id)
            ->where('is_active', true)
            ->with('studentA', 'studentB')
            ->orderByDesc('id')
            ->get()
            ->map(function (LearnerIncompatibility $row) use ($canViewReason) {
                $row->setAttribute('display_reason', ($row->is_confidential && ! $canViewReason) ? __('[restricted]') : $row->reason);

                return $row;
            });

        return view('boarding::allocation.incompatibilities', [
            'incompatibilities' => $incompatibilities,
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(),
        ]);
    }
}
