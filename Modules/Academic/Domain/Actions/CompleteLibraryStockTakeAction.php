<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CompleteLibraryStockTakeData;
use Modules\Academic\Domain\Exceptions\StockTakeNotReadyException;
use Modules\Academic\Models\LibraryCopy;
use Modules\Academic\Models\LibraryStockTake;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;

/**
 * ACT-CompleteLibraryStockTake (Book K ACA-10 §4/BR-ACA-10-009/
 * AC-ACA-10-004). A copy unscanned across the WHOLE stock-take
 * (including its confirmatory second pass) is marked lost; if it was
 * on an active loan at the time, that borrower is charged through the
 * same `FIN-02` ad hoc charge mechanism as every other library charge.
 */
final class CompleteLibraryStockTakeAction extends Action
{
    public function __construct(
        private readonly CreateAdHocChargeAction $createAdHocCharge,
    ) {}

    public function execute(CompleteLibraryStockTakeData $data): LibraryStockTake
    {
        $stockTake = LibraryStockTake::findOrFail($data->stockTakeId);

        if ($stockTake->status !== 'in_progress') {
            throw new InvalidStateTransitionException(
                "Stock-take #{$stockTake->id} in [{$stockTake->status}] cannot be completed.",
                ['stock_take_id' => $stockTake->id, 'status' => $stockTake->status],
            );
        }

        if (! $stockTake->confirmatory_pass_done) {
            throw StockTakeNotReadyException::forStockTake($stockTake->id);
        }

        return $this->transaction(function () use ($stockTake, $data): LibraryStockTake {
            $scannedIds = $stockTake->scanned_copy_ids ?? [];

            $missingCopies = LibraryCopy::query()
                ->where('school_id', $stockTake->school_id)
                ->where('status', '!=', 'withdrawn')
                ->whereNotIn('id', $scannedIds)
                ->get();

            foreach ($missingCopies as $copy) {
                $activeLoan = Loan::query()
                    ->where('school_id', $stockTake->school_id)
                    ->where('copy_id', $copy->id)
                    ->where('status', 'active')
                    ->with('copy.item')
                    ->first();

                if ($activeLoan !== null) {
                    $charge = $this->createAdHocCharge->execute(new CreateAdHocChargeData(
                        schoolId: $stockTake->school_id,
                        academicYearId: $activeLoan->term->academic_year_id,
                        termId: $activeLoan->term_id,
                        studentId: $activeLoan->borrower_id,
                        componentId: $data->feeComponentId,
                        description: "Stock-take lost item: {$activeLoan->copy->item->title}",
                        unitRateMinor: (int) ($activeLoan->copy->item->replacement_cost_minor ?? 0),
                        currency: $activeLoan->copy->item->currency ?? 'USD',
                        raisedByUserId: $data->chargedByUserId,
                        sourceType: 'loan',
                        sourceId: $activeLoan->id,
                        approvedByUserId: $data->chargedByUserId,
                    ));

                    $activeLoan->update(['status' => 'lost', 'fine_charge_id' => $charge->id]);
                }

                $copy->update(['status' => 'lost']);
            }

            $stockTake->update([
                'missing_count' => $missingCopies->count(),
                'status' => 'completed',
            ]);

            return $stockTake->fresh();
        });
    }
}
