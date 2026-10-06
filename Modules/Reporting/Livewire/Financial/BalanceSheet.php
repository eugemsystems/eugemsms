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
use Modules\Reporting\Domain\Actions\GenerateBalanceSheetAction;
use Modules\Reporting\Domain\DataObjects\GenerateBalanceSheetData;

/**
 * `Financial\BalanceSheet` (Book H3 FIN-12 §3/§6, `reporting.report.view`). The
 * position on a date straight from the journal, reproducible for any past date.
 * The result is exploded into plain arrays, the same Livewire-hydration reason
 * `IncomeStatement` gives.
 */
#[Title('Balance sheet')]
#[Layout('layouts.app')]
final class BalanceSheet extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $asAt = '';

    public string $asKnownOn = '';

    public string $currency = '';

    /** @var array<string, array{lines: list<array{account_id: int, code: string, name: string, amount_minor: int}>, total_minor: int}> */
    public array $sections = [];

    public int $currentEarningsMinor = 0;

    public int $totalAssetsMinor = 0;

    public int $totalLiabilitiesAndEquityMinor = 0;

    public bool $isBalanced = true;

    public bool $generated = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('reporting.report.view');

        $this->asAt = now()->toDateString();
        $this->currency = $school->base_currency;
    }

    public function generate(): void
    {
        $this->authorizePermission('reporting.report.view');
        $this->validate([
            'asAt' => ['required', 'date'],
            'asKnownOn' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $result = app(GenerateBalanceSheetAction::class)->execute(new GenerateBalanceSheetData(
            schoolId: $this->school->id,
            asAt: Carbon::parse($this->asAt),
            currency: strtoupper($this->currency),
            asKnownOn: $this->asKnownOn !== '' ? Carbon::parse($this->asKnownOn) : null,
        ));

        $this->sections = $result['sections'];
        $this->currentEarningsMinor = $result['current_earnings_minor'];
        $this->totalAssetsMinor = $result['total_assets_minor'];
        $this->totalLiabilitiesAndEquityMinor = $result['total_liabilities_and_equity_minor'];
        $this->isBalanced = $result['is_balanced'];
        $this->generated = true;
    }

    public function render(): View
    {
        return view('reporting::financial.balance-sheet');
    }
}
