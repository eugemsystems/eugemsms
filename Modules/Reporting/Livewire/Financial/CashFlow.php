<?php

declare(strict_types=1);

namespace Modules\Reporting\Livewire\Financial;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Reporting\Domain\Actions\GenerateCashFlowAction;
use Modules\Reporting\Domain\DataObjects\GenerateCashFlowData;

/**
 * `Financial\CashFlow` (Book H3 FIN-12 §3/§6, `reporting.report.view`). A
 * direct-method statement of bank movements by journal type, between a computed
 * opening and closing position.
 */
#[Title('Cash flow')]
#[Layout('layouts.app')]
final class CashFlow extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $periodStart = '';

    public string $periodEnd = '';

    public string $currency = '';

    /** @var array{opening_minor: int, inflows: list<array{journal_type: string, amount_minor: int}>, outflows: list<array{journal_type: string, amount_minor: int}>, total_inflows_minor: int, total_outflows_minor: int, net_minor: int, closing_minor: int}|null */
    public ?array $result = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('reporting.report.view');

        $this->periodStart = now()->startOfMonth()->toDateString();
        $this->periodEnd = now()->endOfMonth()->toDateString();
        $this->currency = $school->base_currency;
    }

    public function generate(): void
    {
        $this->authorizePermission('reporting.report.view');
        $this->validate([
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date', 'after_or_equal:periodStart'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $this->result = app(GenerateCashFlowAction::class)->execute(new GenerateCashFlowData(
            schoolId: $this->school->id,
            periodStart: Carbon::parse($this->periodStart),
            periodEnd: Carbon::parse($this->periodEnd),
            currency: strtoupper($this->currency),
        ));
    }

    public function render(): View
    {
        return view('reporting::financial.cash-flow');
    }
}
