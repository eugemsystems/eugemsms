<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\GenerateCostOfGenerosityReportAction;

/**
 * `Finance\Reports\Discounts` (Book K FIN-07 §5, `finance.report.discounts`).
 * The cost of the school's own generosity: gross billed, discount granted
 * and net billed — shown separately per scheme and never netted into the
 * headline income figure (BR-FIN-07-014, AC-FIN-07-006). Read from the
 * append-only award utilisation rows, i.e. what was actually posted.
 */
#[Title('Cost of generosity')]
#[Layout('layouts.app')]
final class Discounts extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $termId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.report.discounts');

        $this->termId = Term::query()->where('starts_on', '<=', now())->orderByDesc('starts_on')->value('id');
    }

    public function render(): View
    {
        $term = $this->termId === null ? null : Term::query()->find($this->termId);
        $rows = $term === null ? [] : app(GenerateCostOfGenerosityReportAction::class)->execute($this->school->id, $term->id);

        $totals = [];

        foreach ($rows as $row) {
            $totals[$row->currency] ??= ['gross' => 0, 'discount' => 0, 'net' => 0];
            $totals[$row->currency]['gross'] += $row->grossMinor;
            $totals[$row->currency]['discount'] += $row->discountMinor;
            $totals[$row->currency]['net'] += $row->netMinor;
        }

        return view('finance::reports.discounts', [
            'rows' => $rows,
            'totals' => $totals,
            'terms' => Term::query()->orderByDesc('starts_on')->limit(12)->get(['id', 'name']),
        ]);
    }
}
