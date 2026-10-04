<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Procurement\Quotations;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\AwardQuotationAction;
use Modules\Stores\Domain\Actions\CreateQuotationRequestAction;
use Modules\Stores\Domain\Actions\RecordQuotationAction;
use Modules\Stores\Domain\DataObjects\CreateQuotationRequestData;
use Modules\Stores\Domain\DataObjects\RecordQuotationData;
use Modules\Stores\Models\PurchaseRequisition;
use Modules\Stores\Models\QuotationRequest;
use Modules\Stores\Models\Supplier;

/**
 * `Procurement\Quotations\Compare` (Book H1 FIN-08 §7,
 * `procurement.quotation.manage`). One screen hosts all three steps —
 * request, record, award — the same lifecycle-action-bar shape as
 * `Requisitions\Issue`. Awarding anything other than the lowest
 * compliant bid is refused without a written justification
 * (BR-FIN-08-008); `AwardQuotationAction` itself enforces this, this
 * screen only surfaces the refusal.
 */
#[Title('Compare quotations')]
#[Layout('layouts.app')]
final class Compare extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $requisitionId = null;

    /** @var array<int, int> */
    public array $invitedSupplierIds = [];

    public string $closesOn;

    public ?int $activeRequestId = null;

    public ?int $recordSupplierId = null;

    public string $recordTotalMinor = '';

    public string $justification = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('procurement.quotation.manage');
        $this->closesOn = now()->addDays(14)->toDateString();
    }

    public function createRequest(): void
    {
        $this->validate([
            'requisitionId' => ['required', 'integer'],
            'invitedSupplierIds' => ['array', 'min:1'],
            'closesOn' => ['required', 'date'],
        ]);

        try {
            $request = app(CreateQuotationRequestAction::class)->execute(new CreateQuotationRequestData(
                schoolId: $this->school->id,
                requisitionId: (int) $this->requisitionId,
                supplierIds: array_map('intval', $this->invitedSupplierIds),
                issuedOn: Carbon::now(),
                closesOn: Carbon::parse($this->closesOn),
                requestedByUserId: (int) auth()->id(),
            ));
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->activeRequestId = $request->id;
        $this->toast(__('Quotation request created.'));
    }

    public function recordQuotation(): void
    {
        $this->validate([
            'activeRequestId' => ['required', 'integer'],
            'recordSupplierId' => ['required', 'integer'],
            'recordTotalMinor' => ['required', 'integer', 'min:0'],
        ]);

        app(RecordQuotationAction::class)->execute(new RecordQuotationData(
            schoolId: $this->school->id,
            quotationRequestId: (int) $this->activeRequestId,
            supplierId: (int) $this->recordSupplierId,
            receivedOn: Carbon::now(),
            subtotalMinor: (int) $this->recordTotalMinor,
            taxMinor: 0,
            totalMinor: (int) $this->recordTotalMinor,
            currency: 'USD',
            lines: [],
        ));

        $this->reset(['recordSupplierId', 'recordTotalMinor']);
        $this->toast(__('Quotation recorded.'));
    }

    public function award(int $quotationId): void
    {
        if ($this->activeRequestId === null) {
            return;
        }

        try {
            app(AwardQuotationAction::class)->execute($this->activeRequestId, $quotationId, (int) auth()->id(), $this->justification !== '' ? $this->justification : null);
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Quotation awarded.'));
    }

    public function render(): View
    {
        $quotations = collect();

        if ($this->activeRequestId !== null) {
            $activeRequest = QuotationRequest::with('quotations.supplier')->find($this->activeRequestId);

            if ($activeRequest !== null) {
                $quotations = $activeRequest->quotations;
            }
        }

        return view('stores::procurement.quotations.compare', [
            'requisitions' => PurchaseRequisition::where('school_id', $this->school->id)->where('status', 'approved')->get(),
            'suppliers' => Supplier::where('school_id', $this->school->id)->where('status', 'active')->orderBy('name')->get(),
            'requests' => QuotationRequest::where('school_id', $this->school->id)->orderByDesc('id')->limit(25)->get(),
            'quotations' => $quotations,
        ]);
    }
}
