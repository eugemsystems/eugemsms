<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\DataObjects\CreateReceiptData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\Events\ReceiptPosted;
use Modules\Finance\Domain\Events\SuspenseItemCreated;
use Modules\Finance\Domain\Exceptions\NoOpenTillSessionException;
use Modules\Finance\Domain\Support\AllocationStrategy;
use Modules\Finance\Domain\Support\InvoiceAllocationEngine;
use Modules\Finance\Models\Receipt;
use Modules\Finance\Models\ReceiptAllocation;
use Modules\Finance\Models\ReceiptTender;
use Modules\Finance\Models\SuspenseItem;
use Modules\Finance\Models\Till;
use Modules\Finance\Models\TillSession;

/**
 * ACT-CreateReceipt (Book B FIN-04 §4 ⭐/BR-FIN-04-001/008/010/014
 * (AC-FIN-04-004/007)). Money is receipted the moment it arrives —
 * even an unidentified deposit becomes a posted `Receipt`
 * (`is_suspense = true`) with its own `Dr Cash / Cr Suspense` journal,
 * never held off-ledger.
 *
 * Every tender on one receipt shares the receipt's own `currency` —
 * a single receipt mixing tender currencies is deferred (the schema's
 * per-tender `currency` column stays for when that's built). A cheque
 * tender's amount is excluded from allocation until `ClearChequeAction`
 * clears it (BR-FIN-04-018); its share of the journal credits
 * `unclearedChequeAccountId` instead of a debtor account.
 */
final class CreateReceiptAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly InvoiceAllocationEngine $allocationEngine,
        private readonly PostJournalAction $postJournal,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(CreateReceiptData $data): Receipt
    {
        $session = null;
        $till = null;

        if ($data->tillSessionId !== null) {
            $session = TillSession::find($data->tillSessionId);

            if ($session === null || $session->status !== 'open' || $session->cashier_id !== $data->receivedByUserId) {
                throw NoOpenTillSessionException::forSession($data->tillSessionId);
            }

            $till = Till::findOrFail($session->till_id);
        }

        $currency = Currency::from($data->currency);
        $totalMinor = array_sum(array_column($data->tenders, 'amount_minor'));

        return $this->transaction(function () use ($data, $session, $till, $currency, $totalMinor): Receipt {
            $number = $this->allocateNumber->execute(new AllocateNumberData(
                schoolId: $data->schoolId,
                documentType: 'receipt',
                allocatedByUserId: $data->receivedByUserId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
            ));

            $isSuspense = $data->studentId === null;

            $receipt = Receipt::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'receipt_number' => $number->formatted_number,
                'till_session_id' => $session?->id,
                'receipt_type' => $data->receiptType,
                'payer_type' => $data->payerType,
                'payer_id' => $data->payerId,
                'payer_name' => $data->payerName,
                'payer_phone' => $data->payerPhone,
                'student_id' => $data->studentId,
                'amount_minor' => $totalMinor,
                'currency' => $data->currency,
                'base_amount_minor' => $totalMinor,
                'is_suspense' => $isSuspense,
                'received_at' => Carbon::now(),
                'effective_date' => ($data->effectiveDate ?? Carbon::now())->toDateString(),
                'narration' => $data->narration,
                'status' => 'posted',
                'received_by' => $data->receivedByUserId,
                'created_at' => Carbon::now(),
            ]);

            foreach ($data->tenders as $tender) {
                ReceiptTender::create([
                    'school_id' => $data->schoolId,
                    'receipt_id' => $receipt->id,
                    'tender_type' => $tender['tender_type'],
                    'amount_minor' => $tender['amount_minor'],
                    'currency' => $tender['currency'],
                    'reference' => $tender['reference'] ?? null,
                    'bank_account_id' => $tender['bank_account_id'] ?? null,
                    'is_cleared' => $tender['is_cleared'] ?? ($tender['tender_type'] !== 'cheque'),
                ]);
            }

            $journalLines = $this->debitTenderLines($receipt, $data->tenders, $till, $currency);

            if ($data->gatewayFeeMinor !== null && $data->gatewayFeeMinor > 0) {
                $journalLines = [...$journalLines, ...$this->gatewayFeeLines($data, $journalLines[0]->accountId, $currency)];
            }

            $clearedMinor = $this->clearedMinor($data->tenders);
            $unclearedMinor = $totalMinor - $clearedMinor;
            $allocations = collect();

            if ($isSuspense) {
                $suspenseItem = $this->createSuspenseItem($receipt, $data, $totalMinor);
                $journalLines[] = new JournalLineData(
                    accountId: $data->suspenseAccountId ?? throw new InvalidArgumentException('suspenseAccountId is required for an unidentified receipt.'),
                    direction: 'CR',
                    amount: Money::of($totalMinor, $currency),
                    narration: 'Unidentified deposit — suspense',
                );

                event(new SuspenseItemCreated($suspenseItem));
                $allocatedMinor = 0;
            } else {
                $strategy = $data->allocationStrategy ?? AllocationStrategy::from(
                    (string) $this->settings->get('finance.default_allocation_strategy', new ScopeChain(schoolId: $data->schoolId))
                );

                $result = $this->allocationEngine->allocate(
                    $receipt,
                    $data->studentId,
                    $clearedMinor,
                    $data->currency,
                    $strategy,
                    $data->receivedByUserId,
                    $data->manualInvoiceOrder,
                );

                $allocations = $result['allocations'];

                foreach ($allocations as $allocation) {
                    $journalLines[] = new JournalLineData(
                        accountId: $allocation->component->debtor_account_id,
                        direction: 'CR',
                        amount: Money::of($allocation->amount_minor, $currency),
                        subledgerType: 'guardian',
                        subledgerId: $allocation->invoice->billed_party_id,
                    );
                }

                $allocatedMinor = $clearedMinor - $result['leftoverMinor'];

                if ($result['leftoverMinor'] > 0) {
                    $journalLines[] = new JournalLineData(
                        accountId: $data->creditBalanceAccountId ?? throw new InvalidArgumentException('creditBalanceAccountId is required when a receipt overpays (BR-FIN-04-014).'),
                        direction: 'CR',
                        amount: Money::of($result['leftoverMinor'], $currency),
                        subledgerType: 'student',
                        subledgerId: $data->studentId,
                        narration: 'Overpayment — learner credit balance',
                    );
                }
            }

            if ($unclearedMinor > 0) {
                $journalLines[] = new JournalLineData(
                    accountId: $data->unclearedChequeAccountId ?? throw new InvalidArgumentException('unclearedChequeAccountId is required for a cheque tender.'),
                    direction: 'CR',
                    amount: Money::of($unclearedMinor, $currency),
                    subledgerType: 'student',
                    subledgerId: $data->studentId,
                    narration: 'Uncleared cheque',
                );
            }

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'RECEIPT',
                narration: "Receipt {$receipt->receipt_number} — {$data->payerName}",
                lines: $journalLines,
                effectiveAt: Carbon::now(),
                postedByUserId: $data->receivedByUserId,
                sourceType: 'receipt',
                sourceId: $receipt->id,
            ));

            $fiscalisationStatus = $this->fiscalisationStatus($allocations);

            $receipt->update([
                'journal_id' => $journal->id,
                'allocated_minor' => $allocatedMinor,
                'unallocated_minor' => $totalMinor - $allocatedMinor,
                'fiscalisation_status' => $fiscalisationStatus,
            ]);

            $session?->increment('receipt_count');

            event(new ReceiptPosted($receipt));

            return $receipt->fresh(['tenders', 'allocations']);
        });
    }

    /**
     * @param  array<int, array{tender_type: string, amount_minor: int, currency: string, reference?: string|null, bank_account_id?: int|null, is_cleared?: bool}>  $tenders
     * @return array<int, JournalLineData>
     */
    private function debitTenderLines(Receipt $receipt, array $tenders, ?Till $till, Currency $currency): array
    {
        $lines = [];

        foreach ($tenders as $tender) {
            $accountId = $tender['bank_account_id'] ?? ($till !== null ? ($tender['tender_type'] === 'cash' ? $till->cash_account_id : $till->bank_account_id) : null);

            $lines[] = new JournalLineData(
                accountId: $accountId ?? throw new InvalidArgumentException("Tender [{$tender['tender_type']}] needs an explicit bank_account_id when there is no till session."),
                direction: 'DR',
                amount: Money::of($tender['amount_minor'], $currency),
                narration: "Receipt {$receipt->receipt_number} — {$tender['tender_type']}",
            );
        }

        return $lines;
    }

    /**
     * Book B FIN-05 §6/BR-FIN-05-010. The learner is credited the
     * gross (the allocation amount is untouched); the fee reduces the
     * net actually banked, in the same journal — Dr fee expense,
     * Cr the same account the tender debited, so that account's own
     * net movement becomes gross-minus-fee without the allocation
     * side ever seeing anything but the full gross.
     *
     * @return array<int, JournalLineData>
     */
    private function gatewayFeeLines(CreateReceiptData $data, int $settlementAccountId, Currency $currency): array
    {
        $fee = Money::of($data->gatewayFeeMinor, $currency);

        return [
            new JournalLineData(
                accountId: $data->gatewayFeeExpenseAccountId ?? throw new InvalidArgumentException('gatewayFeeExpenseAccountId is required when gatewayFeeMinor is set.'),
                direction: 'DR',
                amount: $fee,
                narration: 'Gateway fee',
            ),
            new JournalLineData(
                accountId: $settlementAccountId,
                direction: 'CR',
                amount: $fee,
                narration: 'Gateway fee',
            ),
        ];
    }

    /**
     * @param  array<int, array{tender_type: string, amount_minor: int, currency: string, is_cleared?: bool}>  $tenders
     */
    private function clearedMinor(array $tenders): int
    {
        return array_sum(array_map(
            fn (array $tender): int => ($tender['is_cleared'] ?? ($tender['tender_type'] !== 'cheque')) ? $tender['amount_minor'] : 0,
            $tenders,
        ));
    }

    private function createSuspenseItem(Receipt $receipt, CreateReceiptData $data, int $amountMinor): SuspenseItem
    {
        return SuspenseItem::create([
            'school_id' => $data->schoolId,
            'receipt_id' => $receipt->id,
            'source' => $data->receiptType === 'fee' ? 'bank_deposit' : 'cash',
            'amount_minor' => $amountMinor,
            'currency' => $data->currency,
            'reference_text' => $data->narration,
            'depositor_name' => $data->payerName,
            'deposit_date' => ($data->effectiveDate ?? Carbon::now())->toDateString(),
            'status' => 'unidentified',
        ]);
    }

    /**
     * @param  Collection<int, ReceiptAllocation>  $allocations
     */
    private function fiscalisationStatus(Collection $allocations): string
    {
        $hasFiscalisable = $allocations->contains(fn (ReceiptAllocation $allocation): bool => $allocation->component?->is_fiscalisable === true);

        return $hasFiscalisable ? 'queued' : 'not_required';
    }
}
