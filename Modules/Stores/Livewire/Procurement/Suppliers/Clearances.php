<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Procurement\Suppliers;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\CheckTaxClearanceExpiryAction;
use Modules\Stores\Domain\Actions\RecordTaxClearanceAction;
use Modules\Stores\Domain\DataObjects\RecordTaxClearanceData;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierTaxClearance;

/**
 * `Procurement\Suppliers\Clearances` (Book H1 FIN-08 §7 🇿🇼,
 * `procurement.supplier.view`). A new certificate never mutates an
 * earlier one — `RecordTaxClearanceAction` always inserts, since
 * validity is assessed on the INVOICE date, not "whichever certificate
 * is newest" (BR-FIN-08-003 ⭐). The exposure column shows outstanding
 * order value against each expiring certificate's own supplier.
 */
#[Title('Tax clearances')]
#[Layout('layouts.app')]
final class Clearances extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $supplierId = null;

    public string $certificateNumber = '';

    public string $issuedOn;

    public string $expiresOn;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('procurement.supplier.view');
        $this->issuedOn = now()->toDateString();
        $this->expiresOn = now()->addYear()->toDateString();
    }

    public function record(): void
    {
        $this->validate([
            'supplierId' => ['required', 'integer'],
            'certificateNumber' => ['required', 'string', 'max:60'],
            'issuedOn' => ['required', 'date'],
            'expiresOn' => ['required', 'date', 'after:issuedOn'],
        ]);

        app(RecordTaxClearanceAction::class)->execute(new RecordTaxClearanceData(
            schoolId: $this->school->id,
            supplierId: (int) $this->supplierId,
            certificateNumber: $this->certificateNumber,
            issuedOn: Carbon::parse($this->issuedOn),
            expiresOn: Carbon::parse($this->expiresOn),
            verifiedByUserId: (int) auth()->id(),
        ));

        $this->reset(['certificateNumber']);
        $this->toast(__('Tax clearance recorded.'));
    }

    public function render(): View
    {
        return view('stores::procurement.suppliers.clearances', [
            'suppliers' => Supplier::where('school_id', $this->school->id)->orderBy('name')->get(),
            'clearances' => SupplierTaxClearance::with('supplier')->where('school_id', $this->school->id)->orderByDesc('expires_on')->get(),
            'expiring' => app(CheckTaxClearanceExpiryAction::class)->execute($this->school->id),
        ]);
    }
}
