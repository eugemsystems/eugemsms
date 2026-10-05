<?php

declare(strict_types=1);

namespace Modules\Wallet\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Wallet\Models\WalletSale;

/**
 * `Wallet\Reports\Sales` (Book H3 FIN-14 §6, `wallet.report.view`).
 * Read-only — by spend point, by product, by hour of day — a plain
 * aggregation over already-persisted `wallet_sales`/`wallet_sale_lines`,
 * no new Action.
 */
#[Title('Wallet sales analytics')]
#[Layout('layouts.app')]
final class Sales extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('wallet.report.view');
    }

    public function render(): View
    {
        $sales = WalletSale::where('school_id', $this->school->id)->where('status', 'completed')->with('spendPoint')->get();

        $byPoint = $sales->groupBy(fn (WalletSale $sale): string => $sale->spendPoint->name)
            ->map(fn ($rows) => ['count' => $rows->count(), 'total_minor' => (int) $rows->sum('total_minor')]);

        $byHour = $sales->groupBy(fn (WalletSale $sale): string => $sale->sold_at->format('H:00'))
            ->map(fn ($rows) => $rows->count())
            ->sortKeys();

        return view('wallet::reports.sales', [
            'byPoint' => $byPoint,
            'byHour' => $byHour,
            'totalSales' => $sales->count(),
            'totalMinor' => (int) $sales->sum('total_minor'),
        ]);
    }
}
