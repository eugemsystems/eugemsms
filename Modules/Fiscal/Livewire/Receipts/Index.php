<?php

declare(strict_types=1);

namespace Modules\Fiscal\Livewire\Receipts;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Fiscal\Models\FiscalReceipt;

/**
 * `Fiscal\Receipts\Index` (Book H3 FIN-13 §7, `fiscal.view`).
 * Read-only monitor over every fiscal receipt — status, counters
 * (per-currency and global, BR-FIN-13-004), verification code and QR
 * once accepted. Corrections (retry, credit notes) live on
 * `Receipts\Retry`, never here.
 */
#[Title('Fiscal receipts')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $statusFilter = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('fiscal.view');
    }

    public function render(): View
    {
        $query = FiscalReceipt::where('school_id', $this->school->id);

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return view('fiscal::receipts.index', [
            'receipts' => $query->orderByDesc('id')->limit(100)->get(),
        ]);
    }
}
