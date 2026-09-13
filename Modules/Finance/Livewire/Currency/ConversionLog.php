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
use Modules\Finance\Models\CurrencyConversion;

/**
 * `Finance\Currency\ConversionLog` (Book B FIN-06 §6, `finance.rate.view`)
 * — every conversion performed, append-only (BR-FIN-06-003): the rate,
 * source, and both amounts for every conversion, so any figure is
 * re-derivable and challengeable.
 */
#[Title('Conversion log')]
#[Layout('layouts.app')]
final class ConversionLog extends Component
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
        $query = CurrencyConversion::query()->where('school_id', $this->school->id)->orderByDesc('converted_at');

        return view('finance::currency.conversion-log', [
            'conversions' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'context_type' => [
                'label' => __('Context'), 'sortable' => true, 'filter' => 'select',
                'options' => ['journal_line' => __('Journal line'), 'invoice' => __('Invoice'), 'receipt' => __('Receipt'), 'revaluation' => __('Revaluation')],
            ],
            'from_currency' => ['label' => __('From'), 'sortable' => true],
            'to_currency' => ['label' => __('To'), 'sortable' => true],
            'rate_used' => ['label' => __('Rate used')],
            'converted_at' => ['label' => __('Converted at'), 'sortable' => true],
        ];
    }
}
