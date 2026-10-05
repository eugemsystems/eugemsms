<?php

declare(strict_types=1);

namespace Modules\Fiscal\Livewire\Audit;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Fiscal\Models\FiscalAuditLogEntry;

/**
 * `Fiscal\Audit\Index` (Book H3 FIN-13 §7, `fiscal.audit.view`).
 * Read-only raw-payload viewer. **Documented backend gap, found
 * during this pass, not closed by it**: `fiscal_audit_log` has a
 * real migration/model/factory, but no Action anywhere in the
 * domain layer (`RegisterFiscalDeviceAction`, `OpenFiscalDayAction`,
 * `CloseFiscalDayAction`, `SubmitFiscalReceiptAction`,
 * `DrainOfflineFiscalQueueAction`, `CompileAndSubmitZReportAction`)
 * ever writes a row to it (verified by grep — only
 * `FiscalAuditLogEntryFactory`, called by `FiscalServiceProvider`'s
 * own tenancy-isolation-test registration, ever creates one).
 * BR-FIN-13-012 ("every request and response is logged verbatim...
 * append-only") is therefore not actually implemented despite being
 * a named rule. Closing this honestly needs wiring a log write into
 * every one of those call sites — real business logic, not a narrow
 * create-only Action the way `CreatePayGradeNotchAction`/
 * `CreateReportDefinitionAction` closed this pass's other two gaps —
 * so it is deliberately left as a documented gap rather than
 * improvised past. This screen is built and ready for when that
 * wiring lands; it renders correctly empty today.
 */
#[Title('Fiscal audit log')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('fiscal.audit.view');
    }

    public function render(): View
    {
        return view('fiscal::audit.index', [
            'entries' => FiscalAuditLogEntry::where('school_id', $this->school->id)->orderByDesc('occurred_at')->limit(100)->get(),
        ]);
    }
}
