<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Behaviour;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Welfare\Models\BehaviourRecord;

/**
 * `Behaviour\Board` (Book G BRD-07 §5, `behaviour.view`). By class,
 * house, hostel; merit leaders and concerns. A confidential
 * (safeguarding-paused) record shows only that a matter is under
 * review here too — same rule `Behaviour\Learner` applies
 * (BR-BRD-07-018).
 */
#[Title('Behaviour board')]
#[Layout('layouts.app')]
final class Board extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $classId = null;

    public ?int $hostelId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.view');
    }

    public function render(): View
    {
        $query = BehaviourRecord::where('school_id', $this->school->id)
            ->with(['student:id,first_name,last_name,class_id,house_id', 'category:id,name,polarity']);

        if ($this->classId !== null) {
            $query->where('class_id', $this->classId);
        }

        if ($this->hostelId !== null) {
            $query->where('hostel_id', $this->hostelId);
        }

        $records = $query->orderByDesc('occurred_at')->limit(100)->get();

        return view('welfare::behaviour.board', [
            'records' => $records,
        ]);
    }
}
