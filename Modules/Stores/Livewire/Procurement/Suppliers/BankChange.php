<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Procurement\Suppliers;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\ChangeSupplierBankDetailsAction;
use Modules\Stores\Domain\DataObjects\ChangeSupplierBankDetailsData;
use Modules\Stores\Models\Supplier;

/**
 * `Procurement\Suppliers\BankChange` (Book H1 FIN-08 §7,
 * `procurement.supplier.bank_change` ⚠⚠, AC-FIN-08-009). No
 * `supplier_bank_change_requests` table exists in the domain layer —
 * `ChangeSupplierBankDetailsAction` takes both the requester's and the
 * approver's id in one call and enforces they differ itself. This
 * screen stages the proposed values in cache, keyed by supplier,
 * rather than inventing a new persisted table unreviewed: step one
 * records who requested which new values; step two, necessarily a
 * DIFFERENT signed-in user, applies them. The Action's own refusal is
 * what actually prevents a single user completing both steps — this
 * screen only avoids letting the SAME request form double as its own
 * approval button.
 */
#[Title('Change bank details')]
#[Layout('layouts.app')]
final class BankChange extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Supplier $supplier;

    public string $bankName = '';

    public string $bankBranch = '';

    public string $accountNumber = '';

    public string $accountName = '';

    public ?string $swiftCode = null;

    public function mount(School $school, Supplier $supplier): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('procurement.supplier.bank_change');
        $this->supplier = $supplier;
    }

    private function cacheKey(): string
    {
        return "stores.supplier-bank-change.{$this->supplier->id}";
    }

    public function requestChange(): void
    {
        $this->validate([
            'bankName' => ['required', 'string', 'max:80'],
            'bankBranch' => ['required', 'string', 'max:80'],
            'accountNumber' => ['required', 'string', 'max:40'],
            'accountName' => ['required', 'string', 'max:200'],
        ]);

        Cache::put($this->cacheKey(), [
            'bank_name' => $this->bankName,
            'bank_branch' => $this->bankBranch,
            'account_number' => $this->accountNumber,
            'account_name' => $this->accountName,
            'swift_code' => $this->swiftCode,
            'requested_by' => auth()->id(),
        ], now()->addDays(7));

        $this->toast(__('Change requested — a different user must approve it.'));
    }

    public function approve(): void
    {
        $pending = Cache::get($this->cacheKey());

        if ($pending === null) {
            $this->toast(__('No pending bank detail change.'), 'danger');

            return;
        }

        try {
            app(ChangeSupplierBankDetailsAction::class)->execute(new ChangeSupplierBankDetailsData(
                supplierId: $this->supplier->id,
                bankName: $pending['bank_name'],
                bankBranch: $pending['bank_branch'],
                accountNumber: $pending['account_number'],
                accountName: $pending['account_name'],
                requestedByUserId: (int) $pending['requested_by'],
                approvedByUserId: (int) auth()->id(),
                swiftCode: $pending['swift_code'],
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        Cache::forget($this->cacheKey());
        $this->toast(__('Bank details changed — the bursar has been notified.'));
    }

    public function render(): View
    {
        return view('stores::procurement.suppliers.bank-change', [
            'pending' => Cache::get($this->cacheKey()),
        ]);
    }
}
