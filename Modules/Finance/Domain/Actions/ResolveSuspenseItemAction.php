<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\DataObjects\ResolveSuspenseItemData;
use Modules\Finance\Domain\Events\SuspenseItemResolved;
use Modules\Finance\Domain\Support\AllocationStrategy;
use Modules\Finance\Domain\Support\InvoiceAllocationEngine;
use Modules\Finance\Models\SuspenseItem;

/**
 * ACT-ResolveSuspenseItem (Book B FIN-04 §5/BR-FIN-04-011,
 * AC-FIN-04-005). The cashier has already confirmed the match — this
 * action never runs on its own. Posts one `SUSPENSE_ALLOCATION`
 * journal (`Dr Suspense / Cr Fee Debtors`) settling the student's
 * open invoices with the same engine `CreateReceiptAction` uses.
 */
final class ResolveSuspenseItemAction extends Action
{
    public function __construct(
        private readonly InvoiceAllocationEngine $allocationEngine,
        private readonly PostJournalAction $postJournal,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(ResolveSuspenseItemData $data): SuspenseItem
    {
        $suspenseItem = SuspenseItem::findOrFail($data->suspenseItemId);
        $availableMinor = $suspenseItem->amount_minor - $suspenseItem->resolved_minor;

        return $this->transaction(function () use ($suspenseItem, $data, $availableMinor): SuspenseItem {
            $strategy = $data->allocationStrategy ?? AllocationStrategy::from(
                (string) $this->settings->get('finance.default_allocation_strategy', new ScopeChain(schoolId: $suspenseItem->school_id))
            );

            $result = $this->allocationEngine->allocate(
                $suspenseItem->receipt,
                $data->studentId,
                $availableMinor,
                $suspenseItem->currency,
                $strategy,
                $data->resolvedByUserId,
            );

            $settledMinor = $availableMinor - $result['leftoverMinor'];
            $currency = Currency::from($suspenseItem->currency);
            $journalLines = [];

            foreach ($result['allocations'] as $allocation) {
                $journalLines[] = new JournalLineData(
                    accountId: $data->suspenseAccountId,
                    direction: 'DR',
                    amount: Money::of($allocation->amount_minor, $currency),
                    narration: 'Suspense resolved',
                );

                $journalLines[] = new JournalLineData(
                    accountId: $allocation->component->debtor_account_id,
                    direction: 'CR',
                    amount: Money::of($allocation->amount_minor, $currency),
                    subledgerType: 'guardian',
                    subledgerId: $allocation->invoice->billed_party_id,
                    narration: 'Suspense resolved',
                );
            }

            if ($journalLines !== []) {
                $this->postJournal->execute(new PostJournalData(
                    schoolId: $suspenseItem->school_id,
                    academicYearId: $suspenseItem->receipt->academic_year_id,
                    termId: $suspenseItem->receipt->term_id,
                    journalType: 'SUSPENSE_ALLOCATION',
                    narration: "Suspense item [{$suspenseItem->id}] resolved",
                    lines: $journalLines,
                    effectiveAt: Carbon::now(),
                    postedByUserId: $data->resolvedByUserId,
                    sourceType: 'suspense_item',
                    sourceId: $suspenseItem->id,
                ));
            }

            $newResolved = $suspenseItem->resolved_minor + $settledMinor;

            $suspenseItem->update([
                'resolved_minor' => $newResolved,
                'status' => $newResolved >= $suspenseItem->amount_minor ? 'resolved' : 'partially_resolved',
                'resolved_at' => Carbon::now(),
                'resolved_by' => $data->resolvedByUserId,
                'resolution_note' => $data->resolutionNote,
            ]);

            event(new SuspenseItemResolved($suspenseItem));

            return $suspenseItem;
        });
    }
}
