<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Invoices;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Invoice;

/**
 * `Finance\Invoices\Index` (Book B FIN-03 §5, `finance.invoice.view`).
 */
#[Title('Invoices')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.invoice.view');
    }

    public function render(): View
    {
        $query = Invoice::query()->with('student')->orderByDesc('id');

        return view('finance::invoices.index', [
            'invoices' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'invoice_number' => ['label' => __('Number'), 'sortable' => true, 'searchable' => true],
            'invoice_type' => ['label' => __('Type'), 'sortable' => true],
            'due_date' => ['label' => __('Due'), 'sortable' => true],
            'balance_minor' => ['label' => __('Balance'), 'sortable' => true],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['draft' => __('Draft'), 'issued' => __('Issued'), 'partially_paid' => __('Partially paid'), 'paid' => __('Paid'), 'overdue' => __('Overdue'), 'voided' => __('Voided'), 'written_off' => __('Written off')],
            ],
        ];
    }
}
