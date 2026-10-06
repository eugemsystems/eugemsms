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
 * Read-only raw-payload viewer. Rows are written by
 * `AuditedFiscalGatewayDriver` (BR-FIN-13-012), which wraps every
 * gateway call.
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
