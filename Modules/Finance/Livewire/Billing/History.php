<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Billing;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\BillingRun;

/**
 * `Finance\Billing\History` (Book B FIN-02 §7, `finance.billing.view`)
 * — every billing run ever computed for this school. A committed run
 * can never be un-run (BR-FIN-02-017); this is the permanent record.
 */
#[Title('Billing run history')]
#[Layout('layouts.app')]
final class History extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.billing.view');
    }

    public function render(): View
    {
        $query = BillingRun::query()->with('term')->orderByDesc('id');

        return view('finance::billing.history', [
            'runs' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'id' => ['label' => __('Run'), 'sortable' => true],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['computing' => __('Computing'), 'preview' => __('Preview'), 'approved' => __('Approved'), 'committing' => __('Committing'), 'committed' => __('Committed'), 'failed' => __('Failed'), 'cancelled' => __('Cancelled')],
            ],
            'total_learners' => ['label' => __('Learners'), 'sortable' => true],
            'exception_count' => ['label' => __('Exceptions'), 'sortable' => true],
            'total_net_minor' => ['label' => __('Total net'), 'sortable' => true],
        ];
    }
}
