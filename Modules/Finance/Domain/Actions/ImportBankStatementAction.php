<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\ImportBankStatementData;
use Modules\Finance\Domain\Events\BankStatementImported;
use Modules\Finance\Models\BankStatement;
use Modules\Finance\Models\BankStatementLine;
use Modules\Finance\Models\Receipt;

/**
 * ACT-ImportBankStatement (Book B FIN-05 §3/§8/BR-FIN-05-011). Takes
 * already-parsed line data — CSV/OFX upload and column mapping is a
 * screens concern this pass doesn't build. Auto-matches on an exact
 * receipt-number reference straight away (the `JOB-AutoMatchBankStatement`
 * "on import, high-confidence only" trigger); amount/date-proximity and
 * fuzzy-description matching are left for `MatchBankStatementLineAction`
 * to confirm by hand, per BR-FIN-05-011's confidence threshold.
 */
final class ImportBankStatementAction extends Action
{
    public function execute(ImportBankStatementData $data): BankStatement
    {
        return $this->transaction(function () use ($data): BankStatement {
            $statement = BankStatement::create([
                'school_id' => $data->schoolId,
                'bank_account_id' => $data->bankAccountId,
                'statement_from' => $data->statementFrom,
                'statement_to' => $data->statementTo,
                'opening_balance_minor' => $data->openingBalanceMinor,
                'closing_balance_minor' => $data->closingBalanceMinor,
                'currency' => $data->currency,
                'line_count' => count($data->lines),
                'status' => 'matching',
                'imported_by' => $data->importedByUserId,
            ]);

            $matchedCount = 0;

            foreach ($data->lines as $index => $line) {
                $creditMinor = $line['credit_minor'] ?? null;
                $match = $creditMinor !== null ? $this->matchByReference($data->schoolId, $line['reference'] ?? null, $creditMinor, $data->currency) : null;

                $statementLine = BankStatementLine::create([
                    'school_id' => $data->schoolId,
                    'statement_id' => $statement->id,
                    'line_number' => $index + 1,
                    'transaction_date' => $line['transaction_date'],
                    'value_date' => $line['value_date'] ?? null,
                    'description' => $line['description'],
                    'reference' => $line['reference'] ?? null,
                    'debit_minor' => $line['debit_minor'] ?? null,
                    'credit_minor' => $creditMinor,
                    'currency' => $data->currency,
                    'match_status' => $match !== null ? 'auto_matched' : 'unmatched',
                    'matched_type' => $match !== null ? 'receipt' : null,
                    'matched_id' => $match?->id,
                    'match_confidence' => $match !== null ? 100 : null,
                    'matched_by' => $match !== null ? $data->importedByUserId : null,
                    'matched_at' => $match !== null ? Carbon::now() : null,
                ]);

                if ($match !== null) {
                    $matchedCount++;
                }
            }

            $statement->update([
                'matched_count' => $matchedCount,
                'status' => $matchedCount === count($data->lines) ? 'reconciled' : 'matching',
            ]);

            event(new BankStatementImported($statement));

            return $statement->fresh('lines');
        });
    }

    private function matchByReference(int $schoolId, ?string $reference, int $creditMinor, string $currency): ?Receipt
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        return Receipt::query()
            ->where('school_id', $schoolId)
            ->where('receipt_number', $reference)
            ->where('amount_minor', $creditMinor)
            ->where('currency', $currency)
            ->first();
    }
}
