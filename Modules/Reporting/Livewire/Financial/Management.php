<?php

declare(strict_types=1);

namespace Modules\Reporting\Livewire\Financial;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Reporting\Domain\Actions\GenerateCollectionReportAction;
use Modules\Reporting\Domain\Actions\GenerateDepartmentalReportAction;
use Modules\Reporting\Domain\DataObjects\GenerateManagementReportData;

/**
 * `Financial\Management` (Book H3 FIN-12 §3/§6, `reporting.report.view`). Two management views over
 * the same period: income and expense by cost centre (departmental) and fees billed against fees
 * collected by grade level (collection). Both are read from stored figures and never alter anything.
 */
#[Title('Management reports')]
#[Layout('layouts.app')]
final class Management extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $tab = 'departmental';

    public string $periodStart = '';

    public string $periodEnd = '';

    public string $currency = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('reporting.report.view');

        $this->periodStart = now()->startOfYear()->toDateString();
        $this->periodEnd = now()->toDateString();
        $this->currency = $school->base_currency;
    }

    public function render(): View
    {
        $this->authorizePermission('reporting.report.view');
        $this->validate([
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date', 'after_or_equal:periodStart'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $data = new GenerateManagementReportData($this->school->id, Carbon::parse($this->periodStart), Carbon::parse($this->periodEnd), strtoupper($this->currency));

        return view('reporting::financial.management', [
            'report' => $this->tab === 'collection' ? app(GenerateCollectionReportAction::class)->execute($data) : app(GenerateDepartmentalReportAction::class)->execute($data),
        ]);
    }
}
