<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Fiscal\Domain\Events\FiscalReconciliationException;
use Modules\Fiscal\Models\FiscalReceipt;

/**
 * ACT-ReconcileFiscalisation (Book H3 FIN-13 §6/BR-FIN-13-021,
 * `FIN-12`'s own close-checklist "Fiscalisation reconciled" check
 * reads this). Every fiscalisable commercial receipt got a real
 * `FiscalReceipt` row the moment it was routed
 * (`RouteReceiptForFiscalisationAction`) — so the exception set is
 * simply every row not yet `accepted` and older than
 * `fiscal.reconciliation_window_hours`. No cross-module query of
 * `receipts`/`farm_sales` is needed; this table IS the reconciliation
 * ledger.
 */
final class ReconcileFiscalisationAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return Collection<int, FiscalReceipt>
     */
    public function execute(int $schoolId): Collection
    {
        $windowHours = (int) $this->settings->get('fiscal.reconciliation_window_hours', new ScopeChain(schoolId: $schoolId));
        $cutoff = Carbon::now()->subHours($windowHours);

        $unreconciled = FiscalReceipt::where('school_id', $schoolId)
            ->where('status', '!=', 'accepted')
            ->where('receipt_date', '<=', $cutoff)
            ->get();

        if ($unreconciled->isNotEmpty()) {
            event(new FiscalReconciliationException(
                $schoolId,
                $unreconciled->map(fn (FiscalReceipt $r): array => ['source_type' => $r->source_type, 'source_id' => $r->source_id])->all(),
            ));
        }

        return $unreconciled;
    }
}
