<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Allocation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CreateAllocationConstraintAction;
use Modules\Boarding\Domain\DataObjects\CreateAllocationConstraintData;
use Modules\Boarding\Models\AllocationConstraint;
use Modules\Boarding\Models\Hostel;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Allocation\Constraints` (Book F BRD-01 §5, `boarding.hostel.manage`).
 * List + create, via the new gap-filling `CreateAllocationConstraintAction`
 * (see that action's own docblock). `constraint_type` intentionally
 * excludes `gender_match` from the dropdown below — gender segregation
 * is enforced unconditionally in the Action layer itself and is never
 * read from this configurable table, so there is nothing here that
 * could weaken it even by mistake.
 */
#[Title('Allocation constraints')]
#[Layout('layouts.app')]
final class Constraints extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $constraintType = 'level_band';

    public string $severity = 'soft';

    public int $weight = 1;

    public ?int $hostelId = null;

    public ?int $value = null;

    public string $reason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.hostel.manage');
    }

    public function create(): void
    {
        $this->validate([
            'constraintType' => ['required', 'in:level_band,house_affinity,sibling_together,sibling_apart,medical_proximity,mobility_ground_floor,max_level_spread,prefect_room_only,learner_incompatibility'],
            'severity' => ['required', 'in:hard,soft'],
            'weight' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        app(CreateAllocationConstraintAction::class)->execute(new CreateAllocationConstraintData(
            schoolId: $this->school->id,
            constraintType: $this->constraintType,
            severity: $this->severity,
            weight: $this->weight,
            hostelId: $this->hostelId,
            value: $this->value,
            reason: $this->reason !== '' ? $this->reason : null,
        ));

        $this->reset(['value', 'reason']);
        $this->toast(__('Constraint created.'));
    }

    public function render(): View
    {
        return view('boarding::allocation.constraints', [
            'constraints' => AllocationConstraint::where('school_id', $this->school->id)->where('is_active', true)->orderByDesc('id')->get(),
            'hostels' => Hostel::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
