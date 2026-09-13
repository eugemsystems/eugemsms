<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Currency;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\ExchangeRate;

/**
 * `Finance\Currency\Rates` (Book B FIN-06 §6, `finance.rate.view`) —
 * rate history per pair, with effective ranges and source.
 * `exchange_rates` is append-only, so this is a pure read screen; a
 * correction shows up as a new row, never a mutated one.
 */
#[Title('Exchange rates')]
#[Layout('layouts.app')]
final class Rates extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.rate.view');
    }

    public function render(): View
    {
        $query = ExchangeRate::query()->with('source', 'capturedBy')->orderByDesc('effective_from');

        return view('finance::currency.rates', [
            'rates' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'from_currency' => ['label' => __('From'), 'sortable' => true, 'filter' => 'select', 'options' => ['USD' => 'USD', 'ZWG' => 'ZWG']],
            'to_currency' => ['label' => __('To'), 'sortable' => true, 'filter' => 'select', 'options' => ['USD' => 'USD', 'ZWG' => 'ZWG']],
            'rate' => ['label' => __('Rate'), 'sortable' => true],
            'effective_from' => ['label' => __('Effective from'), 'sortable' => true],
            'status' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['pending' => __('Pending'), 'active' => __('Active'), 'superseded' => __('Superseded'), 'rejected' => __('Rejected')],
            ],
        ];
    }
}
