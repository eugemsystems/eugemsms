<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Farm\Domain\DataObjects\TransferToKitchenData;
use Modules\Farm\Domain\Events\ProduceTransferredToKitchen;
use Modules\Farm\Domain\Events\WithdrawalPeriodBlocked;
use Modules\Farm\Domain\Exceptions\WithdrawalPeriodActiveException;
use Modules\Farm\Models\InternalTransfer;
use Modules\Farm\Models\Livestock;
use Modules\Farm\Models\LivestockEvent;
use Modules\Farm\Models\ProductionOutput;
use Modules\Stores\Domain\Actions\DispatchStockTransferAction;
use Modules\Stores\Domain\Actions\ReceiveStockTransferAction;
use Modules\Stores\Domain\DataObjects\DispatchStockTransferData;

/**
 * ACT-TransferToKitchen (Book H2 OPS-03 §2/§3 ⭐⭐/BR-OPS-03-008/009/
 * 012 ⭐/AC-OPS-03-002/003). Never a free transfer: moves real `FIN-09`
 * stock between the farm and kitchen stores at internal cost through
 * the same dispatch → receive pipeline `FIN-09`'s own inter-store
 * transfer uses, posting one real journal (Dr Kitchen Inventory / Cr
 * Farm Inventory). Milk or meat linked to livestock still within a
 * recorded withdrawal period is refused outright before any stock
 * moves — the hard food-safety control BR-OPS-03-012 requires.
 */
final class TransferToKitchenAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly DispatchStockTransferAction $dispatchTransfer,
        private readonly ReceiveStockTransferAction $receiveTransfer,
    ) {}

    public function execute(TransferToKitchenData $data): InternalTransfer
    {
        if ($data->outputId !== null) {
            $output = ProductionOutput::findOrFail($data->outputId);

            if (in_array($output->output_type, ['milk', 'meat'], true)) {
                $this->assertNoActiveWithdrawal($data->productionUnitId, $data->transferDate);
            }
        }

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'internal_transfer',
            allocatedByUserId: $data->dispatchedByUserId,
            academicYearId: $data->academicYearId,
            termId: $data->termId,
        ));

        return $this->transaction(function () use ($data, $number): InternalTransfer {
            $dispatched = $this->dispatchTransfer->execute(new DispatchStockTransferData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                fromStoreId: $data->fromStoreId,
                toStoreId: $data->toStoreId,
                reason: 'farm_to_kitchen',
                items: [['itemId' => $data->itemId, 'quantity' => $data->quantity]],
                dispatchedByUserId: $data->dispatchedByUserId,
            ));
            $line = $dispatched->lines->first();

            $received = $this->receiveTransfer->execute(
                $dispatched->id,
                [$line->id => (float) $line->quantity_dispatched],
                $data->dispatchedByUserId,
                $data->academicYearId,
                $data->termId,
            );

            $transfer = InternalTransfer::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'transfer_number' => $number->formatted_number,
                'production_unit_id' => $data->productionUnitId,
                'from_store_id' => $data->fromStoreId,
                'to_store_id' => $data->toStoreId,
                'transfer_date' => $data->transferDate->toDateString(),
                'harvest_id' => $data->harvestId,
                'output_id' => $data->outputId,
                'item_id' => $data->itemId,
                'quantity' => $data->quantity,
                'unit' => $data->unit,
                'unit_cost_minor' => $line->unit_cost_minor,
                'total_cost_minor' => $line->line_cost_minor,
                'currency' => $line->currency,
                'market_price_minor' => $data->marketPriceMinor,
                'journal_id' => $received->journal_id,
                'dispatched_by' => $data->dispatchedByUserId,
                'received_by' => $data->dispatchedByUserId,
                'status' => $received->status,
            ]);

            event(new ProduceTransferredToKitchen($transfer));

            return $transfer;
        });
    }

    private function assertNoActiveWithdrawal(int $productionUnitId, CarbonInterface $transferDate): void
    {
        $livestockIds = Livestock::where('production_unit_id', $productionUnitId)->pluck('id');

        $activeWithdrawal = LivestockEvent::whereIn('livestock_id', $livestockIds)
            ->whereNotNull('withdrawal_ends_on')
            ->where('withdrawal_ends_on', '>=', $transferDate->toDateString())
            ->orderByDesc('withdrawal_ends_on')
            ->first();

        if ($activeWithdrawal === null) {
            return;
        }

        $livestock = Livestock::findOrFail($activeWithdrawal->livestock_id);
        $withdrawalEndsOn = Carbon::parse($activeWithdrawal->withdrawal_ends_on);

        event(new WithdrawalPeriodBlocked($livestock, $withdrawalEndsOn));

        throw WithdrawalPeriodActiveException::forLivestock($livestock->id, $withdrawalEndsOn);
    }
}
