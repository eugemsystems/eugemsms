<?php

declare(strict_types=1);

namespace Modules\Fiscal\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Fiscal\Models\FiscalZReport;

/**
 * `Fiscal\Reports\ZReports` (Book H3 FIN-13 §7, `fiscal.view`).
 * Read-only — compiled and submitted exclusively through
 * `Days\Index`'s own "Compile Z-report" control
 * (`CompileAndSubmitZReportAction`), never from here.
 */
#[Title('Z-reports')]
#[Layout('layouts.app')]
final class ZReports extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('fiscal.view');
    }

    public function render(): View
    {
        return view('fiscal::reports.z-reports', [
            'reports' => FiscalZReport::where('school_id', $this->school->id)->orderByDesc('report_date')->get(),
        ]);
    }
}
