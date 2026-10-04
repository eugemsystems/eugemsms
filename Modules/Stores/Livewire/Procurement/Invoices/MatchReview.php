<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Procurement\Invoices;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Stores\Domain\Actions\ApproveSupplierInvoiceAction;
use Modules\Stores\Models\SupplierInvoice;

/**
 * `Procurement\Invoices\Match` (Book H1 FIN-08 §7 ⭐, `procurement.invoice.approve` ⚠).
 * Every variance is presented, never hidden — `match_variance_minor`
 * and `match_status` are both real figures `RegisterSupplierInvoiceAction`
 * already computed and stored at registration; this screen's only job
 * is to show them and call the approval Action, which itself refuses
 * an `unmatched` non-service invoice (BR-FIN-08-017).
 */
#[Title('Match review')]
#[Layout('layouts.app')]
final class MatchReview extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $grnAccrualAccountId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('procurement.invoice.approve');
    }

    public function approve(int $invoiceId): void
    {
        if ($this->grnAccrualAccountId === null) {
            $this->toast(__('Select the GRN accrual account first.'), 'danger');

            return;
        }

        try {
            app(ApproveSupplierInvoiceAction::class)->execute($invoiceId, (int) auth()->id(), (int) $this->grnAccrualAccountId);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Invoice approved for payment.'));
    }

    public function render(): View
    {
        return view('stores::procurement.invoices.match', [
            'invoices' => SupplierInvoice::with('supplier')
                ->where('school_id', $this->school->id)
                ->whereIn('status', ['received', 'under_review'])
                ->orderByDesc('id')
                ->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
