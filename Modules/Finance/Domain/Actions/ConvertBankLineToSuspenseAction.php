<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\ConvertBankLineToSuspenseData;
use Modules\Finance\Domain\DataObjects\CreateReceiptData;
use Modules\Finance\Domain\DataObjects\MatchBankStatementLineData;
use Modules\Finance\Models\BankStatementLine;
use Modules\Finance\Models\Receipt;

/**
 * ACT-ConvertBankLineToSuspense (Book B FIN-05 §5/BR-FIN-05-012,
 * AC-FIN-05-006). "Money on the bank statement is always on the
 * ledger" — a bank credit that matches no receipt at all still
 * becomes a real, posted `Receipt` (via `FIN-04`'s own suspense
 * path), never a row that just sits unresolved on the statement.
 */
final class ConvertBankLineToSuspenseAction extends Action
{
    public function __construct(
        private readonly CreateReceiptAction $createReceipt,
        private readonly MatchBankStatementLineAction $matchLine,
    ) {}

    public function execute(ConvertBankLineToSuspenseData $data): Receipt
    {
        $line = BankStatementLine::findOrFail($data->bankStatementLineId);
        $statement = $line->statement;

        return $this->transaction(function () use ($line, $statement, $data): Receipt {
            $receipt = $this->createReceipt->execute(new CreateReceiptData(
                schoolId: $statement->school_id,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                receiptType: 'fee',
                payerType: 'external',
                payerName: $line->description,
                currency: $line->currency,
                tenders: [[
                    'tender_type' => 'bank_transfer',
                    'amount_minor' => $line->credit_minor,
                    'currency' => $line->currency,
                    'reference' => $line->reference,
                    'bank_account_id' => $statement->bankAccount->gl_account_id,
                ]],
                receivedByUserId: $data->convertedByUserId,
                narration: "Unmatched bank credit — {$line->description}",
                suspenseAccountId: $data->suspenseAccountId,
                effectiveDate: Carbon::parse($line->transaction_date),
            ));

            $this->matchLine->execute(new MatchBankStatementLineData(
                bankStatementLineId: $line->id,
                matchedType: 'receipt',
                matchedId: $receipt->id,
                matchConfidence: 100,
                matchedByUserId: $data->convertedByUserId,
            ));

            return $receipt;
        });
    }
}
