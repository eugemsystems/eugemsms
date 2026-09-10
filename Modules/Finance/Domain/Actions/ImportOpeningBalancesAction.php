<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\ImportOpeningBalancesData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\OpeningBalanceLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\Events\OpeningBalancesImported;
use Modules\Finance\Models\Journal;

/**
 * ACT-ImportOpeningBalances (Book B FIN-01 §5/BR-FIN-01-025). Posts a
 * single `OPENING_BALANCE` journal through the normal posting engine —
 * `PostJournalAction`'s own per-currency balance assertion is exactly
 * "the whole import is atomic and rejected if the resulting trial
 * balance doesn't balance": there's no separate atomicity mechanism to
 * build, because an unbalanced set of lines never becomes a journal in
 * the first place.
 */
final class ImportOpeningBalancesAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(ImportOpeningBalancesData $data): Journal
    {
        $lines = array_map(fn (OpeningBalanceLineData $line): JournalLineData => new JournalLineData(
            accountId: $line->accountId,
            direction: $line->direction,
            amount: $line->amount,
            subledgerType: $line->subledgerType,
            subledgerId: $line->subledgerId,
        ), $data->lines);

        $journal = $this->postJournal->execute(new PostJournalData(
            schoolId: $data->schoolId,
            academicYearId: $data->academicYearId,
            termId: $data->termId,
            journalType: 'OPENING_BALANCE',
            narration: 'Opening balance import',
            lines: $lines,
            effectiveAt: $data->effectiveAt,
            postedByUserId: $data->importedByUserId,
        ));

        event(new OpeningBalancesImported($data->schoolId, [$journal->id]));

        return $journal;
    }
}
