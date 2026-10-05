<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Executive;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Livewire\Concerns\BuildsKpiTiles;

/**
 * `Intelligence\Executive\BursarDashboard` (Book J INT-02 §4,
 * `executive.dashboard.view.finance`). The same colour-coded tiles as
 * the head's, narrowed to the finance KPIs (module code `FIN-*`), with
 * links into the Finance module's own reports — no parallel detail
 * screens (BR-INT-02-007).
 */
#[Title('Bursar dashboard')]
#[Layout('layouts.app')]
final class BursarDashboard extends Component
{
    use AuthorizesPermissions;
    use BuildsKpiTiles;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('executive.dashboard.view.finance');
    }

    public function render(): View
    {
        return view('intelligence::executive.bursar-dashboard', [
            'tiles' => $this->kpiTiles('FIN'),
            'reportLinks' => array_filter([
                __('Fee collections') => Route::has('finance.reports.collections') ? route('finance.reports.collections', $this->school) : null,
                __('Aged debtors') => Route::has('finance.reports.aged-debtors') ? route('finance.reports.aged-debtors', $this->school) : null,
                __('Trial balance') => Route::has('finance.reports.trial-balance') ? route('finance.reports.trial-balance', $this->school) : null,
            ]),
        ]);
    }
}
