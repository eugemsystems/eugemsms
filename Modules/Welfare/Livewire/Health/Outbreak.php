<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Health;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Welfare\Models\SickBayAdmission;

/**
 * `Health\Outbreak` (Book G BRD-06 §5/BR-BRD-06-023, `health.clinical.view`
 * — aggregate only). No Action exists to list outbreak clusters —
 * `AdmitToSickBayAction` checks the same threshold on every admission
 * and fires the `OutbreakThresholdReached` event itself, but nothing
 * reads it back as a dashboard. This screen is a plain, read-only
 * aggregate count by presenting complaint within the configured window
 * — never a per-learner clinical field, so it is safe to build without
 * going through `ResolveMedicalTierAction`.
 */
#[Title('Outbreak monitor')]
#[Layout('layouts.app')]
final class Outbreak extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('health.clinical.view');
    }

    public function render(): View
    {
        $scope = new ScopeChain(schoolId: $this->school->id);
        $settings = app(SettingResolver::class);
        $windowDays = (int) $settings->get('health.outbreak_window_days', $scope);
        $threshold = (int) $settings->get('health.outbreak_threshold_cases', $scope);

        $windowStart = Carbon::now()->subDays($windowDays);

        $clusters = SickBayAdmission::query()
            ->where('school_id', $this->school->id)
            ->where('admitted_at', '>=', $windowStart)
            ->selectRaw('presenting_complaint, count(*) as case_count')
            ->groupBy('presenting_complaint')
            ->orderByDesc('case_count')
            ->get();

        return view('welfare::health.outbreak', [
            'clusters' => $clusters,
            'threshold' => $threshold,
            'windowDays' => $windowDays,
        ]);
    }
}
