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
use Modules\Reporting\Domain\Actions\GenerateIncomeStatementAction;
use Modules\Reporting\Domain\DataObjects\GenerateIncomeStatementData;

/**
 * `Reports\Financial\IncomeStatement` (Book H3 FIN-12 §3/§6 ⭐,
 * `reporting.report.view`). Folds the spec's own separate
 * "Point-in-time" screen into this one read — both read the exact
 * same `GenerateIncomeStatementAction`/`IncomeStatementResult`, which
 * already carries `reconcilingItems`/`reconcilingTotalMinor`
 * whenever `asKnownOn` is given (BR-FIN-12-004/005, AC-FIN-12-002/003)
 * — a second route would only duplicate this one's form and table.
 * `IncomeStatementResult` itself is exploded into plain scalar/array
 * public properties rather than held as one object — the same
 * Livewire-hydration gotcha `Modules\Utilities\Livewire\Dashboard\Index`
 * already hit for `OutageCostResult` ("Property type not supported in
 * Livewire" for a plain readonly DTO).
 */
#[Title('Income statement')]
#[Layout('layouts.app')]
final class IncomeStatement extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $periodStart = '';

    public string $periodEnd = '';

    public string $asKnownOn = '';

    /** @var array<int, array{account_id: int, code: string, name: string, amount_minor: int}> */
    public array $lines = [];

    public int $netMinor = 0;

    /** @var array<int, array{account_id: int, code: string, name: string, amount_minor: int}> */
    public array $reconcilingItems = [];

    public int $reconcilingTotalMinor = 0;

    public bool $generated = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('reporting.report.view');

        $this->periodStart = now()->startOfMonth()->toDateString();
        $this->periodEnd = now()->endOfMonth()->toDateString();
    }

    public function generate(): void
    {
        $this->validate([
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date'],
        ]);

        $result = app(GenerateIncomeStatementAction::class)->execute(new GenerateIncomeStatementData(
            schoolId: $this->school->id,
            periodStart: Carbon::parse($this->periodStart),
            periodEnd: Carbon::parse($this->periodEnd),
            asKnownOn: $this->asKnownOn !== '' ? Carbon::parse($this->asKnownOn) : null,
        ));

        $this->lines = $result->lines;
        $this->netMinor = $result->netMinor;
        $this->reconcilingItems = $result->reconcilingItems;
        $this->reconcilingTotalMinor = $result->reconcilingTotalMinor;
        $this->generated = true;
    }

    public function render(): View
    {
        return view('reporting::financial.income-statement');
    }
}
