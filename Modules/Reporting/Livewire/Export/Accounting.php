<?php

declare(strict_types=1);

namespace Modules\Reporting\Livewire\Export;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Reporting\Domain\Actions\GenerateAccountingExportAction;
use Modules\Reporting\Domain\DataObjects\GenerateAccountingExportData;
use Modules\Reporting\Models\AccountingExport;

/**
 * `Reports\Export\Accounting` (Book H3 FIN-12 §4/§6 ⚠,
 * `reporting.report.export`). `generic_csv` is the one real target
 * format built — a real QuickBooks/Sage/Pastel column mapping needs
 * that product's own documented import schema, which this pass
 * doesn't have (`GenerateAccountingExportAction`'s own docblock). An
 * overlapping export range is not refused, only flagged
 * (`DuplicateAccountingExportAttempted`), matching the Action's own
 * behaviour exactly — this screen does not add a client-side block
 * the Action itself doesn't have.
 */
#[Title('Accounting export')]
#[Layout('layouts.app')]
final class Accounting extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $targetSystem = 'generic_csv';

    public string $periodFrom = '';

    public string $periodTo = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('reporting.report.export');

        $this->periodFrom = now()->startOfMonth()->toDateString();
        $this->periodTo = now()->endOfMonth()->toDateString();
    }

    public function export(): void
    {
        $this->validate([
            'targetSystem' => ['required', 'in:quickbooks,sage,pastel,generic_csv'],
            'periodFrom' => ['required', 'date'],
            'periodTo' => ['required', 'date'],
        ]);

        app(GenerateAccountingExportAction::class)->execute(new GenerateAccountingExportData(
            schoolId: $this->school->id,
            targetSystem: $this->targetSystem,
            periodFrom: Carbon::parse($this->periodFrom),
            periodTo: Carbon::parse($this->periodTo),
            exportedByUserId: (int) auth()->id(),
        ));

        $this->toast(__('Accounting export generated.'));
    }

    public function render(): View
    {
        return view('reporting::export.accounting', [
            'exports' => AccountingExport::where('school_id', $this->school->id)->orderByDesc('id')->get(),
        ]);
    }
}
