<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\MatchBankStatementLineData;
use Modules\Finance\Models\BankStatementLine;

/**
 * ACT-MatchBankStatementLine (Book B FIN-05 §8/BR-FIN-05-011). The
 * matching workbench's "one-click match" — a human confirming a
 * suggested (or entirely manual) candidate. Below-threshold
 * suggestions never apply themselves; this action is the only path
 * from `unmatched` to a matched state.
 */
final class MatchBankStatementLineAction extends Action
{
    public function execute(MatchBankStatementLineData $data): BankStatementLine
    {
        $line = BankStatementLine::findOrFail($data->bankStatementLineId);

        return $this->transaction(function () use ($line, $data): BankStatementLine {
            $line->update([
                'match_status' => 'manually_matched',
                'matched_type' => $data->matchedType,
                'matched_id' => $data->matchedId,
                'match_confidence' => $data->matchConfidence,
                'matched_by' => $data->matchedByUserId,
                'matched_at' => Carbon::now(),
            ]);

            $statement = $line->statement;
            $matchedCount = $statement->lines()->whereIn('match_status', ['auto_matched', 'manually_matched'])->count();

            $statement->update([
                'matched_count' => $matchedCount,
                'status' => $matchedCount === $statement->line_count ? 'reconciled' : 'matching',
            ]);

            return $line;
        });
    }
}
