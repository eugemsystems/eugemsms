<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Farm\Domain\DataObjects\RecordFarmSaleData;
use Modules\Farm\Domain\Events\FarmSaleRecorded;
use Modules\Farm\Models\FarmSale;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;

/**
 * ACT-RecordFarmSale (Book H2 OPS-03 §2/BR-OPS-03-016). Posts
 * `Dr Cash / Cr Farm Sales Income` directly — `Modules\Finance`'s own
 * `CreateReceiptAction` (`FIN-04`) is built around student fee
 * receipting (invoice allocation, till sessions) and doesn't fit an
 * external buyer with no invoice, so this posts its own journal the
 * same way `Modules\Utilities\Domain\Actions\PurchasePrepaidTokenAction`
 * does for a cost that doesn't cleanly fit an existing engine either.
 * `fiscal_receipt_id` stays null — `FIN-13` doesn't exist yet, see
 * `FarmSaleRecorded`'s own docblock.
 */
final class RecordFarmSaleAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(RecordFarmSaleData $data): FarmSale
    {
        $totalMinor = (int) round($data->quantity * $data->unitPriceMinor);
        $currency = Currency::from($data->currency);

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'farm_sale',
            allocatedByUserId: $data->performedByUserId,
            academicYearId: $data->academicYearId,
            termId: $data->termId,
        ));

        return $this->transaction(function () use ($data, $totalMinor, $currency, $number): FarmSale {
            $lines = [
                new JournalLineData(accountId: $data->cashAccountId, direction: 'DR', amount: Money::of($totalMinor, $currency)),
                new JournalLineData(accountId: $data->salesIncomeAccountId, direction: 'CR', amount: Money::of($totalMinor, $currency)),
            ];

            if ($data->costOfSalesMinor !== null && $data->costOfSalesMinor > 0 && $data->inventoryAccountId !== null) {
                $lines[] = new JournalLineData(accountId: $data->salesIncomeAccountId, direction: 'DR', amount: Money::of($data->costOfSalesMinor, $currency), narration: 'Cost of sales');
                $lines[] = new JournalLineData(accountId: $data->inventoryAccountId, direction: 'CR', amount: Money::of($data->costOfSalesMinor, $currency));
            }

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'FARM_SALE',
                narration: "Farm sale {$number->formatted_number} — {$data->buyerName}",
                lines: $lines,
                effectiveAt: $data->saleDate,
                postedByUserId: $data->performedByUserId,
                sourceType: 'farm_sale',
            ));

            $sale = FarmSale::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'sale_number' => $number->formatted_number,
                'production_unit_id' => $data->productionUnitId,
                'sale_date' => $data->saleDate->toDateString(),
                'buyer_name' => $data->buyerName,
                'buyer_contact' => $data->buyerContact,
                'item_description' => $data->itemDescription,
                'quantity' => $data->quantity,
                'unit' => $data->unit,
                'unit_price_minor' => $data->unitPriceMinor,
                'total_minor' => $totalMinor,
                'currency' => $data->currency,
                'cost_of_sales_minor' => $data->costOfSalesMinor,
                'journal_id' => $journal->id,
            ]);

            event(new FarmSaleRecorded($sale, $data->performedByUserId));

            return $sale;
        });
    }
}
