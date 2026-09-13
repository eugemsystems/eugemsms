<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\CostCentres;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Finance\Domain\Actions\CreateCostCentreAction;
use Modules\Finance\Domain\DataObjects\CreateCostCentreData;
use Modules\Finance\Models\CostCentre;

/**
 * `Finance\CostCentres\Index` (Book B FIN-01 §8). Only
 * `CreateCostCentreAction` exists in the domain layer — there is no
 * update/deactivate action for a cost centre — so, mirroring
 * `Core\CustomFields\Index`'s precedent of only offering actions the
 * domain layer actually backs, this screen is list + create only.
 */
#[Title('Cost centres')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use Toasts;

    public bool $showCreateModal = false;

    public string $code = '';

    public string $name = '';

    public ?int $parentId = null;

    public ?int $sectionId = null;

    public ?int $managerUserId = null;

    public bool $isProfitCentre = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.cost_centre.view');
    }

    public function openCreateModal(): void
    {
        $this->authorizePermission('finance.cost_centre.manage');
        $this->reset(['code', 'name', 'parentId', 'sectionId', 'managerUserId', 'isProfitCentre']);
        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function create(): void
    {
        $this->authorizePermission('finance.cost_centre.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            'parentId' => ['nullable', 'integer'],
            'sectionId' => ['nullable', 'integer'],
            'managerUserId' => ['nullable', 'integer'],
        ]);

        try {
            app(CreateCostCentreAction::class)->execute(new CreateCostCentreData(
                schoolId: $this->school->id,
                code: $this->code,
                name: $this->name,
                parentId: $this->parentId,
                sectionId: $this->sectionId,
                managerUserId: $this->managerUserId,
                isProfitCentre: $this->isProfitCentre,
            ));
        } catch (DomainException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        $this->showCreateModal = false;
        $this->toast(__('Cost centre created.'));
    }

    public function render(): View
    {
        $query = CostCentre::query()->with('parent', 'section', 'manager')->orderBy('code');

        return view('finance::cost-centres.index', [
            'costCentres' => $this->paginateDataTable($query, $this->tableColumns()),
            'parentCandidates' => CostCentre::query()->orderBy('code')->get(),
            'sections' => SchoolSection::query()->where('school_id', $this->school->id)->orderBy('name')->get(),
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
            'is_profit_centre' => [
                'label' => __('Profit centre'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Yes'), '0' => __('No')],
            ],
            'is_active' => [
                'label' => __('Active'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Yes'), '0' => __('No')],
            ],
        ];
    }
}
