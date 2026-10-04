<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Health;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Welfare\Models\EmergencyCarePlan;
use Modules\Welfare\Models\MedicalCondition;

/**
 * `Health\Alerts` (Book G BRD-06 §5, `health.actionable.view` — Tier 2).
 * Only `public_summary` ever reaches this screen — `name` and
 * `diagnosis_notes` (Tier 3) are never selected (BR-BRD-06-001/
 * AC-BRD-06-001/008). A matron or duty staff member scans this board
 * for who needs care and what to do, not a diagnosis.
 */
#[Title('Medical alert board')]
#[Layout('layouts.app')]
final class Alerts extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $search = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('health.actionable.view');
    }

    public function render(): View
    {
        $query = MedicalCondition::query()
            ->where('school_id', $this->school->id)
            ->where('status', 'active')
            ->whereNotNull('public_summary')
            ->with(['student' => fn ($q) => $q->select(['id', 'first_name', 'last_name', 'class_id', 'house_id'])])
            ->orderByRaw("CASE severity WHEN 'life_threatening' THEN 1 WHEN 'severe' THEN 2 WHEN 'moderate' THEN 3 ELSE 4 END");

        if (trim($this->search) !== '') {
            $term = trim($this->search);
            $query->whereHas('student', function ($q) use ($term): void {
                $q->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%");
            });
        }

        $conditions = $query->limit(200)->get(['id', 'student_id', 'severity', 'public_summary', 'requires_emergency_plan', 'affects_dietary', 'affects_physical_activity']);

        $planStatus = EmergencyCarePlan::whereIn('student_id', $conditions->pluck('student_id'))
            ->where('is_active', true)
            ->get()
            ->groupBy('student_id')
            ->map(fn ($plans) => $plans->contains(fn (EmergencyCarePlan $p): bool => $p->approved_at !== null));

        return view('welfare::health.alerts', [
            'conditions' => $conditions,
            'planStatus' => $planStatus,
        ]);
    }
}
