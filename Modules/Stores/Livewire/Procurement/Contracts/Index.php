<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Procurement\Contracts;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\CreateSupplierContractAction;
use Modules\Stores\Domain\Actions\RenewSupplierContractAction;
use Modules\Stores\Domain\Actions\TerminateSupplierContractAction;
use Modules\Stores\Domain\DataObjects\CreateSupplierContractData;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierContract;

/**
 * `Procurement\Contracts\Index` (Book H1 FIN-08 §7, `procurement.supplier.view` to
 * look, `procurement.supplier.manage` to record, renew or terminate). Contracts
 * nearing their renewal-notice window show as expiring; an auto-renewing one is
 * flagged because it renews unless someone cancels in time (BR-FIN-08-024).
 */
#[Title('Supplier contracts')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $supplierId = null;

    public string $contractNumber = '';

    public string $title = '';

    public string $contractType = 'supply';

    public string $startsOn = '';

    public string $endsOn = '';

    public string $valueMinor = '';

    public string $renewalNoticeDays = '';

    public bool $autoRenew = false;

    /** @var array<int, string> contractId => proposed new end date */
    public array $renewals = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('procurement.supplier.view');
        $this->startsOn = now()->toDateString();
    }

    public function create(): void
    {
        $this->authorizePermission('procurement.supplier.manage');
        $this->resetErrorBag();

        $this->validate([
            'supplierId' => ['required', 'integer'],
            'contractNumber' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:200'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['nullable', 'date'],
            'valueMinor' => ['nullable', 'integer', 'min:0'],
            'renewalNoticeDays' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        try {
            app(CreateSupplierContractAction::class)->execute(new CreateSupplierContractData(
                schoolId: $this->school->id,
                supplierId: (int) $this->supplierId,
                contractNumber: $this->contractNumber,
                title: $this->title,
                contractType: $this->contractType,
                startsOn: Carbon::parse($this->startsOn),
                endsOn: $this->endsOn !== '' ? Carbon::parse($this->endsOn) : null,
                valueMinor: $this->valueMinor !== '' ? (int) $this->valueMinor : null,
                currency: $this->valueMinor !== '' ? $this->school->base_currency : null,
                renewalNoticeDays: $this->renewalNoticeDays !== '' ? (int) $this->renewalNoticeDays : null,
                autoRenew: $this->autoRenew,
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->reset(['contractNumber', 'title', 'endsOn', 'valueMinor', 'renewalNoticeDays', 'autoRenew']);
        $this->toast(__('Contract recorded.'));
    }

    public function renew(int $contractId): void
    {
        $this->authorizePermission('procurement.supplier.manage');
        $newEnd = $this->renewals[$contractId] ?? '';

        if ($newEnd === '' || strtotime($newEnd) === false) {
            $this->toast(__('Enter the new end date first.'), 'danger');

            return;
        }

        $this->run(fn () => app(RenewSupplierContractAction::class)->execute(SupplierContract::query()->findOrFail($contractId)->id, Carbon::parse($newEnd)), __('Contract renewed.'));
    }

    public function terminate(int $contractId): void
    {
        $this->authorizePermission('procurement.supplier.manage');

        $this->run(fn () => app(TerminateSupplierContractAction::class)->execute(SupplierContract::query()->findOrFail($contractId)->id), __('Contract terminated.'));
    }

    private function run(callable $callback, string $success): void
    {
        try {
            $callback();
        } catch (InvalidStateTransitionException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast($success);
    }

    public function render(): View
    {
        return view('stores::procurement.contracts.index', [
            'suppliers' => Supplier::query()->where('school_id', $this->school->id)->where('status', '!=', 'blacklisted')->orderBy('name')->get(['id', 'name']),
            'contracts' => SupplierContract::query()->with('supplier:id,name')->orderByRaw("case status when 'expiring' then 0 when 'active' then 1 else 2 end")->orderBy('ends_on')->limit(200)->get(),
            'types' => CreateSupplierContractAction::TYPES,
        ]);
    }
}
