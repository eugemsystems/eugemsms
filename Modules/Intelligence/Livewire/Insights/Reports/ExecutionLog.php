<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Insights\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\ReportExecution;

/**
 * `Intelligence\Reports\ExecutionLog` (Book J INT-01 §5,
 * `report.view_audit`). Every execution — saved or thrown away, live
 * or redirected — is logged (BR-INT-01-009), so this shows which
 * reports actually get used and by whom. It lists who ran what, how
 * many rows and how long; never a report's *results*. Read-only.
 */
#[Title('Report execution log')]
#[Layout('layouts.app')]
final class ExecutionLog extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('report.view_audit');
    }

    public function render(): View
    {
        return view('intelligence::insights.reports.execution-log', [
            'executions' => ReportExecution::with(['report:id,name', 'executor:id,name'])
                ->where('school_id', $this->school->id)->orderByDesc('executed_at')->limit(100)->get(),
        ]);
    }
}
