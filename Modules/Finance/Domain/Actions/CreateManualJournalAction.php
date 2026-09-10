<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\Events\ManualJournalSubmitted;
use Modules\Finance\Domain\Support\JournalAssembler;
use Modules\Finance\Models\Journal;

/**
 * ACT-CreateManualJournal (Book B FIN-01 §5/BR-FIN-01-018). Runs the
 * same structural and balance validation as `PostJournalAction` (via
 * the shared `JournalAssembler`) but lands as `draft` — no financial
 * audit entry, no cache update, no `JournalPosted` event, because it
 * isn't posted yet. Only `ApproveManualJournalAction` can make it real,
 * and only a different user than the one who created it.
 */
final class CreateManualJournalAction extends Action
{
    public function __construct(
        private readonly JournalAssembler $assembler,
    ) {}

    public function execute(PostJournalData $data): Journal
    {
        return $this->transaction(function () use ($data): Journal {
            $journal = $this->assembler->assemble($data, 'draft');

            event(new ManualJournalSubmitted($journal));

            return $journal;
        });
    }
}
