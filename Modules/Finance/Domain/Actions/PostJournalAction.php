<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordFinancialAuditEntryAction;
use Modules\Core\Domain\DataObjects\Audit\RecordFinancialAuditEntryData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\Events\JournalPosted;
use Modules\Finance\Domain\Support\JournalAssembler;
use Modules\Finance\Models\Journal;

/**
 * ACT-PostJournal (Book B FIN-01 §5/§6 ⭐/BR-FIN-01-001). The single
 * write path for all money. Every other financial event in the
 * platform — a fee bill, a receipt, a credit note, a payroll run —
 * ultimately calls this. Financial audit is recorded synchronously in
 * the same transaction (BR-CORE-08's own rule, honoured here rather
 * than queued) so a journal and its audit entry either both land or
 * both roll back.
 */
final class PostJournalAction extends Action
{
    public function __construct(
        private readonly JournalAssembler $assembler,
        private readonly RecordFinancialAuditEntryAction $recordFinancialAudit,
    ) {}

    public function execute(PostJournalData $data): Journal
    {
        return $this->transaction(function () use ($data): Journal {
            $journal = $this->assembler->assemble($data, 'posted');

            $totalDebitBaseMinor = $journal->lines->where('direction', 'DR')->sum('base_amount_minor');

            $this->recordFinancialAudit->execute(new RecordFinancialAuditEntryData(
                schoolId: $data->schoolId,
                eventType: 'journal_posted',
                subjectType: 'journal',
                subjectId: $journal->id,
                causerId: $data->postedByUserId,
                academicYearId: $data->academicYearId,
                payload: [
                    'journal_number' => $journal->journal_number,
                    'journal_type' => $journal->journal_type,
                    'line_count' => $journal->lines->count(),
                ],
                amount: Money::of((int) $totalDebitBaseMinor, Currency::from($journal->lines->first()->base_currency)),
                termId: $data->termId,
            ));

            event(new JournalPosted($journal));

            return $journal;
        });
    }
}
