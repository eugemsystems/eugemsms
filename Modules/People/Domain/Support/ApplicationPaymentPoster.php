<?php

declare(strict_types=1);

namespace Modules\People\Domain\Support;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Models\Receipt;
use Modules\Finance\Models\ReceiptTender;
use Modules\People\Models\Application;

/**
 * Book C PPL-02 §3/BR-PPL-02-004. Shared by `PayApplicationFeeAction`
 * and `PayAcceptanceDepositAction` — both receipt money against an
 * applicant who has no `Student` row yet, so neither can go through
 * `FIN-04`'s `CreateReceiptAction` (which always treats a null
 * `studentId` as an unidentified suspense deposit — genuinely correct
 * there, wrong here: an application payment is fully identified, it
 * just isn't a student yet). This poster creates the `Receipt` row
 * directly instead, with `is_suspense` always `false`.
 */
final class ApplicationPaymentPoster
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly PostJournalAction $postJournal,
    ) {}

    public function post(
        Application $application,
        int $termId,
        string $receiptType,
        int $amountMinor,
        string $currency,
        string $tenderType,
        int $bankAccountId,
        int $targetAccountId,
        int $receivedByUserId,
    ): Receipt {
        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $application->school_id,
            documentType: 'receipt',
            allocatedByUserId: $receivedByUserId,
            academicYearId: $application->intake->academic_year_id,
            termId: $termId,
        ));

        $receipt = Receipt::create([
            'school_id' => $application->school_id,
            'academic_year_id' => $application->intake->academic_year_id,
            'term_id' => $termId,
            'receipt_number' => $number->formatted_number,
            'receipt_type' => $receiptType,
            'payer_type' => 'guardian',
            'payer_name' => $application->fullName().' — application '.$application->application_number,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'base_amount_minor' => $amountMinor,
            'allocated_minor' => 0,
            'unallocated_minor' => $amountMinor,
            'is_suspense' => false,
            'received_at' => Carbon::now(),
            'effective_date' => Carbon::now()->toDateString(),
            'narration' => "Application {$application->application_number}",
            'status' => 'posted',
            'received_by' => $receivedByUserId,
            'created_at' => Carbon::now(),
        ]);

        ReceiptTender::create([
            'school_id' => $application->school_id,
            'receipt_id' => $receipt->id,
            'tender_type' => $tenderType,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'bank_account_id' => $bankAccountId,
            'is_cleared' => true,
        ]);

        $money = Money::of($amountMinor, Currency::from($currency));

        $journal = $this->postJournal->execute(new PostJournalData(
            schoolId: $application->school_id,
            academicYearId: $application->intake->academic_year_id,
            termId: $termId,
            journalType: 'RECEIPT',
            narration: "Receipt {$receipt->receipt_number} — {$receiptType}",
            lines: [
                new JournalLineData(accountId: $bankAccountId, direction: 'DR', amount: $money, narration: $receiptType),
                new JournalLineData(accountId: $targetAccountId, direction: 'CR', amount: $money, narration: $receiptType, subledgerType: 'application', subledgerId: $application->id),
            ],
            effectiveAt: Carbon::now(),
            postedByUserId: $receivedByUserId,
            sourceType: 'application',
            sourceId: $application->id,
        ));

        $receipt->update(['journal_id' => $journal->id]);

        return $receipt->fresh();
    }
}
