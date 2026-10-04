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
 * `Behaviour\Analytics` (Book G BRD-07 §5/BR-BRD-07-019,
 * `behaviour.report.view`). Aggregate counts by category and polarity
 * only — no Action computes this, and no per-student ranking is ever
 * shown, matching the spec's own "never for public ranking of
 * learners".
 */
#[Title('Behaviour analytics')]
#[Layout('layouts.app')]
final class Analytics extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.report.view');
    }

    public function render(): View
    {
        $byCategory = BehaviourRecord::where('school_id', $this->school->id)
            ->where('is_confidential', false)
            ->join('behaviour_categories', 'behaviour_categories.id', '=', 'behaviour_records.category_id')
            ->selectRaw('behaviour_categories.name as category_name, behaviour_records.polarity, count(*) as total')
            ->groupBy('behaviour_categories.name', 'behaviour_records.polarity')
            ->orderByDesc('total')
            ->get();

        return view('welfare::behaviour.analytics', [
            'byCategory' => $byCategory,
        ]);
    }
}
