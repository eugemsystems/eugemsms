<?php

declare(strict_types=1);

namespace Modules\Operations\Livewire\Maintenance;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\Operations\Domain\Actions\MarkFaultReportDuplicateAction;
use Modules\Operations\Domain\Actions\RejectFaultReportAction;
use Modules\Operations\Domain\Actions\TriageFaultReportAction;
use Modules\Operations\Domain\DataObjects\TriageFaultReportData;
use Modules\Operations\Models\FaultReport;
use Modules\People\Models\Staff;
use Modules\Stores\Models\Supplier;

/**
 * `Maintenance\Triage` (Book H2 OPS-02 §7/BR-OPS-02-002/003 ⭐,
 * `maintenance.triage`). Safety-flagged reports always sort first,
 * regardless of stated severity (BR-OPS-02-002) — `render()` orders on
 * `affects_safety desc` before `reported_at`, not the other way round.
 * Hosts all three triage outcomes (convert/duplicate/reject) as one
 * action bar per report, the same lifecycle fold `Procurement\Orders\Index`
 * uses for its own approve/cancel/close-short.
 */
#[Title('Fault triage')]
#[Layout('layouts.app')]
final class Triage extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    /** @var array<int, string> */
    public array $workType = [];

    /** @var array<int, string> */
    public array $title = [];

    /** @var array<int, string> */
    public array $priority = [];

    /** @var array<int, string> */
    public array $assignedTeam = [];

    /** @var array<int, string> */
    public array $costCentreId = [];

    /** @var array<int, string> */
    public array $contractorSupplierId = [];

    /** @var array<int, string> */
    public array $duplicateOf = [];

    /** @var array<int, string> */
    public array $rejectionReason = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('maintenance.triage');
    }

    public function convert(int $reportId): void
    {
        $this->authorizePermission('maintenance.triage');

        try {
            $workOrder = app(TriageFaultReportAction::class)->execute($reportId, new TriageFaultReportData(
                academicYearId: (int) SessionContext::yearId(),
                workType: $this->workType[$reportId] ?? 'corrective',
                title: $this->title[$reportId] ?? 'Fault repair',
                priority: $this->priority[$reportId] ?? 'normal',
                assignedTeam: $this->assignedTeam[$reportId] ?? 'in_house',
                costCentreId: (int) ($this->costCentreId[$reportId] ?? 0),
                currency: $this->school->base_currency,
                triagedByUserId: (int) auth()->id(),
                contractorSupplierId: ($this->contractorSupplierId[$reportId] ?? '') !== '' ? (int) $this->contractorSupplierId[$reportId] : null,
            ));
        } catch (ValidationException|DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Converted to work order :number.', ['number' => $workOrder->work_order_number]));
    }

    public function markDuplicate(int $reportId): void
    {
        $this->authorizePermission('maintenance.triage');

        try {
            app(MarkFaultReportDuplicateAction::class)->execute($reportId, (int) ($this->duplicateOf[$reportId] ?? 0), (int) auth()->id());
        } catch (ValidationException|DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Marked as a duplicate.'));
    }

    public function reject(int $reportId): void
    {
        $this->authorizePermission('maintenance.triage');

        try {
            app(RejectFaultReportAction::class)->execute($reportId, $this->rejectionReason[$reportId] ?? '', (int) auth()->id());
        } catch (ValidationException|DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Fault report rejected.'));
    }

    public function render(): View
    {
        return view('operations::maintenance.triage', [
            'reports' => FaultReport::where('school_id', $this->school->id)
                ->where('status', 'reported')
                ->orderByDesc('affects_safety')
                ->orderBy('reported_at')
                ->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'suppliers' => Supplier::where('school_id', $this->school->id)->orderBy('name')->get(),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->limit(100)->get(),
        ]);
    }
}
