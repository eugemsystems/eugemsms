<?php

declare(strict_types=1);

namespace Modules\Utilities\Livewire\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Utilities\Domain\Actions\ComputeOutageCostAction;

/**
 * `Dashboard\Index` (Book H2 OPS-04 §6 ⭐, `utilities.report.view`).
 * Folds the spec's separate "Consumption analysis" screen into this
 * one read, alongside the cost-of-outage figure — grid vs generator
 * vs solar, for the period the user picks. The board-ready sentence
 * this book's own §4 names comes straight from `OutageCostResult`.
 */
#[Title('Energy dashboard')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $periodStart = '';

    public string $periodEnd = '';

    public bool $hasResult = false;

    public float $gridKwh = 0.0;

    public int $gridCostMinor = 0;

    public float $generatorKwh = 0.0;

    public int $generatorCostMinor = 0;

    public float $outageHours = 0.0;

    public string $currency = 'USD';

    public int $additionalCostMinor = 0;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('utilities.report.view');
        $this->periodStart = Carbon::now()->startOfMonth()->toDateString();
        $this->periodEnd = Carbon::now()->endOfMonth()->toDateString();
        $this->compute();
    }

    public function compute(): void
    {
        $result = app(ComputeOutageCostAction::class)->execute(
            $this->school->id,
            Carbon::parse($this->periodStart),
            Carbon::parse($this->periodEnd),
        );

        $this->hasResult = true;
        $this->gridKwh = $result->gridKwh;
        $this->gridCostMinor = $result->gridCostMinor;
        $this->generatorKwh = $result->generatorKwh;
        $this->generatorCostMinor = $result->generatorCostMinor;
        $this->outageHours = $result->outageHours;
        $this->currency = $result->currency;
        $this->additionalCostMinor = $result->additionalCostMinor();
    }

    public function render(): View
    {
        return view('utilities::dashboard.index');
    }
}
