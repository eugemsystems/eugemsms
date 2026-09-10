<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\AccountBalance;
use Modules\Stores\Domain\Events\RegisterLedgerDivergence;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\FixedAsset;

/**
 * ACT-ReconcileAssetRegister (Book H1 FIN-10 §6/BR-FIN-10-019/
 * AC-FIN-10-008). Runs per category, since each category owns its own
 * asset/accumulated-depreciation account pair — a single school-wide
 * total would hide which category actually diverged. Compares the
 * register's own cost and accumulated-depreciation totals against
 * `account_balances` (`FIN-01`'s own cache, rebuilt from
 * `journal_lines` — never recomputed from raw ledger rows here).
 */
final class ReconcileAssetRegisterAction extends Action
{
    /**
     * @return Collection<int, array{categoryId: int, registerCostMinor: int, ledgerAssetMinor: int, registerAccumDepMinor: int, ledgerAccumDepMinor: int}>
     */
    public function execute(int $schoolId): Collection
    {
        $categories = AssetCategory::where('school_id', $schoolId)->get();
        $divergences = collect();

        foreach ($categories as $category) {
            $registerCost = (int) FixedAsset::where('school_id', $schoolId)->where('category_id', $category->id)->where('status', '!=', 'disposed')->sum('acquisition_cost_minor');
            $registerAccumDep = (int) FixedAsset::where('school_id', $schoolId)->where('category_id', $category->id)->where('status', '!=', 'disposed')->sum('accumulated_depreciation_minor');

            $ledgerAsset = (int) AccountBalance::where('school_id', $schoolId)->where('account_id', $category->asset_account_id)->sum('closing_minor');
            $ledgerAccumDep = (int) AccountBalance::where('school_id', $schoolId)->where('account_id', $category->accum_depreciation_account_id)->sum('closing_minor');

            if ($registerCost !== $ledgerAsset || $registerAccumDep !== abs($ledgerAccumDep)) {
                $divergences->push([
                    'categoryId' => $category->id,
                    'registerCostMinor' => $registerCost,
                    'ledgerAssetMinor' => $ledgerAsset,
                    'registerAccumDepMinor' => $registerAccumDep,
                    'ledgerAccumDepMinor' => abs($ledgerAccumDep),
                ]);

                event(new RegisterLedgerDivergence($schoolId, $category->id, $registerCost, $ledgerAsset));
            }
        }

        return $divergences;
    }
}
