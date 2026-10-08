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
use Modules\Reporting\Domain\Actions\GenerateTrialBalanceAction;
use Modules\Reporting\Domain\DataObjects\GenerateTrialBalanceData;

/**
 * `Reports\Financial\TrialBalance` (Book H3 FIN-12 §3/§6 ⭐,
 * `reporting.report.trial_balance` — registered under module code
 * `REPORTING`, not the spec's own literal `finance.report.*` prefix,
 * the same "module code becomes the permission prefix" divergence
 * `Modules\Stores` already established for `FIN-08`–`11`'s
 * `INVENTORY`/`PROCUREMENT`/`ASSETS`/`BUDGET` codes). A pure query
 * over `journal_lines` — producible for any date, including a fully
 * closed and archived period, and reproduces identically no matter
 * when it's re-run (BR-FIN-12-003). `asKnownOn` also now surfaces
 * `reconcilingItems` whenever given (BR-FIN-12-004/005, built
 * 2026-10-07, mirroring `Financial\IncomeStatement`'s own already-built
 * treatment of the identical rule) — a prior-period adjustment shows
 * on its own, never blended into the rows above it.
 */
#[Title('Trial balance')]
#[Layout('layouts.app')]
final class TrialBalance extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $asAt = '';

    public string $asKnownOn = '';

    /** @var array<int, array{account_id: int, code: string, name: string, currency: string, debit_minor: int, credit_minor: int}> */
    public array $rows = [];

    /** @var array<int, array{account_id: int, code: string, name: string, currency: string, debit_minor: int, credit_minor: int}> */
    public array $reconcilingItems = [];

    public bool $generated = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('reporting.report.trial_balance');

        $this->asAt = now()->toDateString();
    }

    public function generate(): void
    {
        $this->validate(['asAt' => ['required', 'date']]);

        $result = app(GenerateTrialBalanceAction::class)->execute(new GenerateTrialBalanceData(
            schoolId: $this->school->id,
            asAt: Carbon::parse($this->asAt),
            asKnownOn: $this->asKnownOn !== '' ? Carbon::parse($this->asKnownOn) : null,
        ));

        $this->rows = $result->rows;
        $this->reconcilingItems = $result->reconcilingItems;
        $this->generated = true;
    }

    public function render(): View
    {
        return view('reporting::financial.trial-balance');
    }
}
