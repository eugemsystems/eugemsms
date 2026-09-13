<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Receipts;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Receipt;

/**
 * `Finance\Receipts\Show` (Book B FIN-04 §4, `finance.receipt.view`).
 */
#[Title('Receipt')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Receipt $receipt;

    public function mount(School $school, Receipt $receipt): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.receipt.view');

        $this->receipt = $receipt->load(['tenders', 'allocations.invoice', 'allocations.component', 'student', 'tillSession.till', 'receivedBy']);
    }

    public function render(): View
    {
        return view('finance::receipts.show');
    }
}
