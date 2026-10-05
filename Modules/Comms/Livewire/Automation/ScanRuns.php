<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Automation;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Models\ScanRun;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Automation\ScanRuns` (Book I COM-02 §5, `automation.view`).
 * Three consecutive completed runs matching nothing is surfaced here
 * too — `RuleMatchedZeroRecords` (AC-COM-02-007) is far more likely a
 * broken condition than a genuinely empty population.
 */
#[Title('Automation scan history')]
#[Layout('layouts.app')]
final class ScanRuns extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('automation.view');
    }

    public function render(): View
    {
        $runs = ScanRun::with('rule:id,name')->where('school_id', $this->school->id)->orderByDesc('ran_at')->limit(100)->get();

        $zeroMatchRuleIds = $runs->where('status', 'completed')->groupBy('rule_id')
            ->filter(fn ($ruleRuns) => $ruleRuns->take(3)->count() === 3 && $ruleRuns->take(3)->every(fn (ScanRun $run): bool => $run->records_matched === 0))
            ->keys()->all();

        return view('comms::automation.scan-runs', ['runs' => $runs, 'zeroMatchRuleIds' => $zeroMatchRuleIds]);
    }
}
