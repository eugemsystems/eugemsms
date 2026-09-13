<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Fees;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CreateFeeComponentAction;
use Modules\Finance\Domain\DataObjects\CreateFeeComponentData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\FeeComponent;

/**
 * `Finance\Fees\Components` (Book B FIN-02 §7, `finance.fee_component.manage`)
 * — the fee catalogue. Only `CreateFeeComponentAction` exists in the
 * domain layer (same "list + create only" precedent as
 * `Finance\CostCentres\Index`) — there is no update action, so a
 * mis-configured component is deactivated and replaced, not edited.
 */
#[Title('Fee components')]
#[Layout('layouts.app')]
final class Components extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use Toasts;

    public bool $showCreateModal = false;

    public string $code = '';

    public string $name = '';

    public ?string $description = null;

    public string $category = 'tuition';

    public ?int $incomeAccountId = null;

    public ?int $debtorAccountId = null;

    public ?int $costCentreId = null;

    public string $defaultCurrency = 'USD';

    public bool $isRefundable = false;

    public bool $isMandatory = true;

    public bool $isFiscalisable = false;

    public string $taxCategory = 'exempt';

    public int $allocationPriority = 100;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.fee_component.manage');
    }

    public function openCreateModal(): void
    {
        $this->reset([
            'code', 'name', 'description', 'category', 'incomeAccountId', 'debtorAccountId',
            'costCentreId', 'defaultCurrency', 'isRefundable', 'isMandatory', 'isFiscalisable',
            'taxCategory', 'allocationPriority',
        ]);
        $this->category = 'tuition';
        $this->defaultCurrency = 'USD';
        $this->isMandatory = true;
        $this->taxCategory = 'exempt';
        $this->allocationPriority = 100;
        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'in:tuition,levy,boarding,transport,activity,examination,material,deposit,other'],
            'incomeAccountId' => ['required', 'integer'],
            'debtorAccountId' => ['required', 'integer'],
            'costCentreId' => ['nullable', 'integer'],
            'defaultCurrency' => ['required', 'size:3'],
            'taxCategory' => ['required', 'in:standard,zero,exempt'],
            'allocationPriority' => ['required', 'integer', 'min:1'],
        ]);

        try {
            app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
                schoolId: $this->school->id,
                code: $this->code,
                name: $this->name,
                category: $this->category,
                incomeAccountId: (int) $this->incomeAccountId,
                debtorAccountId: (int) $this->debtorAccountId,
                defaultCurrency: $this->defaultCurrency,
                createdByUserId: (int) Auth::id(),
                description: $this->description,
                costCentreId: $this->costCentreId,
                isRefundable: $this->isRefundable,
                isMandatory: $this->isMandatory,
                isFiscalisable: $this->isFiscalisable,
                taxCategory: $this->taxCategory,
                allocationPriority: $this->allocationPriority,
            ));
        } catch (DomainException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        $this->showCreateModal = false;
        $this->toast(__('Fee component created.'));
    }

    public function render(): View
    {
        $query = FeeComponent::query()->with('incomeAccount', 'debtorAccount')->orderBy('sort_order')->orderBy('code');

        return view('finance::fees.components', [
            'components' => $this->paginateDataTable($query, $this->tableColumns()),
            'accounts' => Account::query()->where('is_postable', true)->orderBy('code')->get(),
            'costCentres' => CostCentre::query()->orderBy('code')->get(),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'code' => ['label' => __('Code'), 'sortable' => true, 'searchable' => true],
            'name' => ['label' => __('Name'), 'sortable' => true, 'searchable' => true],
            'category' => ['label' => __('Category'), 'sortable' => true],
            'default_currency' => ['label' => __('Currency'), 'sortable' => true],
            'is_mandatory' => [
                'label' => __('Mandatory'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Yes'), '0' => __('No')],
            ],
            'is_active' => [
                'label' => __('Active'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Yes'), '0' => __('No')],
            ],
        ];
    }
}
