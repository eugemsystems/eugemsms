<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\GenerateStatementData;
use Modules\Finance\Domain\DataObjects\Statement;
use Modules\Finance\Domain\DataObjects\StatementLine;
use Modules\Finance\Models\JournalLine;

/**
 * ACT-GenerateStatement (Book B FIN-03 §5/BR-FIN-03-009/021,
 * AC-FIN-03-008). Built from `journal_lines` for the date range, never
 * from any invoice's cached figures — reproducible byte-for-byte for
 * any closed period, forever, the same guarantee every other report in
 * this book gives. A subledger (student or guardian) is always a
 * debit-normal balance (it is, definitionally, someone who owes the
 * school money) regardless of which specific debtor control account a
 * given line posted against, so `DR` always increases the running
 * balance and `CR` always decreases it here.
 */
final class GenerateStatementAction extends Action
{
    protected bool $transactional = false;

    public function execute(GenerateStatementData $data): Statement
    {
        $baseQuery = JournalLine::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->where('subledger_type', $data->subledgerType)
            ->where('subledger_id', $data->subledgerId)
            ->where('currency', $data->currency);

        $openingLines = (clone $baseQuery)
            ->where('effective_at', '<', $data->from->copy()->startOfDay())
            ->get(['direction', 'amount_minor']);

        $openingBalanceMinor = (int) $openingLines->sum(
            fn (JournalLine $line): int => $line->direction === 'DR' ? $line->amount_minor : -$line->amount_minor,
        );

        $periodLines = (clone $baseQuery)
            ->whereBetween('effective_at', [$data->from->copy()->startOfDay(), $data->to->copy()->endOfDay()])
            ->with('journal')
            ->orderBy('effective_at')
            ->orderBy('id')
            ->get();

        $running = $openingBalanceMinor;
        $lines = [];

        foreach ($periodLines as $line) {
            $signed = $line->direction === 'DR' ? $line->amount_minor : -$line->amount_minor;
            $running += $signed;

            $lines[] = new StatementLine(
                effectiveAt: $line->effective_at->toDateString(),
                journalNumber: $line->journal->journal_number,
                narration: $line->narration ?? $line->journal->narration,
                direction: $line->direction,
                amountMinor: $line->amount_minor,
                runningBalanceMinor: $running,
            );
        }

        return new Statement(
            openingBalanceMinor: $openingBalanceMinor,
            lines: $lines,
            closingBalanceMinor: $running,
            currency: $data->currency,
        );
    }
}
