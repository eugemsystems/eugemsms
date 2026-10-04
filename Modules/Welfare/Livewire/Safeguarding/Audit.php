<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Safeguarding;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Welfare\Livewire\Safeguarding\Concerns\BlocksVendorAndImpersonation;
use Modules\Welfare\Models\SafeguardingAuditEntry;

/**
 * `Safeguarding\Audit` (Book G BRD-08 §6 ⭐, access: lead + governor —
 * `safeguarding.audit.review`). Every access, break-glass highlighted.
 * This screen reads the audit stream's OWN fields (event type, user,
 * access basis, IP, timestamp) — never a case's encrypted content —
 * so it is safe to query directly; it is the record of accesses, not
 * case content itself.
 */
#[Title('Safeguarding audit')]
#[Layout('layouts.app')]
final class Audit extends Component
{
    use AuthorizesPermissions;
    use BlocksVendorAndImpersonation;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->abortIfVendorOrImpersonating();
        $this->authorizePermission('safeguarding.audit.review');
    }

    public function render(): View
    {
        return view('welfare::safeguarding.audit', [
            'entries' => SafeguardingAuditEntry::where('school_id', $this->school->id)
                ->orderByDesc('sequence')
                ->limit(200)
                ->get(),
        ]);
    }
}
