<?php

declare(strict_types=1);

namespace Modules\Operations\Livewire\Maintenance;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Operations\Domain\Actions\ReportFaultAction;
use Modules\Operations\Domain\DataObjects\ReportFaultData;
use Modules\Operations\Models\MaintenanceAsset;

/**
 * `Maintenance\Report` (Book H2 OPS-02 §7 — "any user"). Deliberately
 * the one screen in this module with no `authorizePermission()` call:
 * the spec's own screen table names the permission as literally "any
 * user", matching `BR-OPS-02-001`'s "and nothing else" — location,
 * description and severity are the only required facts.
 * `loadSchool()` still enforces school assignment, the one check this
 * screen genuinely needs.
 */
#[Title('Report a fault')]
#[Layout('layouts.app')]
final class Report extends Component
{
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $location = '';

    public ?int $maintenanceAssetId = null;

    public string $category = 'other';

    public string $description = '';

    public string $severity = 'routine';

    public bool $affectsSafety = false;

    public bool $affectsTeaching = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
    }

    public function report(): void
    {
        $this->validate([
            'location' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'severity' => ['required', 'in:emergency,urgent,routine,cosmetic'],
            'category' => ['required', 'string'],
        ]);

        $report = app(ReportFaultAction::class)->execute(new ReportFaultData(
            schoolId: $this->school->id,
            termId: (int) SessionContext::termId(),
            maintenanceAssetId: $this->maintenanceAssetId,
            location: $this->location,
            category: $this->category,
            description: $this->description,
            photoFileIds: null,
            severity: $this->severity,
            affectsSafety: $this->affectsSafety,
            affectsTeaching: $this->affectsTeaching,
            reportedByUserId: (int) auth()->id(),
        ));

        $this->reset(['location', 'maintenanceAssetId', 'description', 'affectsSafety', 'affectsTeaching']);
        $this->toast(__('Fault reported — reference :ref.', ['ref' => $report->report_number]));
    }

    public function render(): View
    {
        return view('operations::maintenance.report', [
            'assets' => MaintenanceAsset::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
