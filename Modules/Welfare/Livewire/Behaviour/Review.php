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
 * `Behaviour\Review` (Book G BRD-07 §5, `behaviour.review`). Records
 * requiring head/HOD review (`category.requires_head_review`), always
 * excluding `is_confidential` rows — a record paused for safeguarding
 * never surfaces here regardless of its category's own review flag,
 * since BRD-08 §3 withdraws general-staff visibility the moment a
 * record is routed.
 */
#[Title('Behaviour review queue')]
#[Layout('layouts.app')]
final class Review extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.review');
    }

    public function render(): View
    {
        return view('welfare::behaviour.review', [
            'records' => BehaviourRecord::where('school_id', $this->school->id)
                ->where('is_confidential', false)
                ->whereHas('category', fn ($q) => $q->where('requires_head_review', true))
                ->with(['student:id,first_name,last_name', 'category:id,name'])
                ->orderByDesc('occurred_at')
                ->limit(100)
                ->get(),
        ]);
    }
}
