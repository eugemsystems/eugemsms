<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordFinancialAuditEntryAction;
use Modules\Core\Domain\DataObjects\Audit\RecordFinancialAuditEntryData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\DataObjects\ApproveManualJournalData;
use Modules\Finance\Domain\Events\JournalPosted;
use Modules\Finance\Domain\Events\ManualJournalApproved;
use Modules\Finance\Models\Journal;

/**
 * ACT-ApproveManualJournal (Book B FIN-01 §5/BR-FIN-01-018/AC-FIN-01-010).
 * The moment a draft manual journal becomes real money: financial audit
 * and `JournalPosted` fire here, not at draft creation, because until
 * this runs the draft is not yet posted and never affected a balance.
 */
final class ApproveManualJournalAction extends Action
{
    public function __construct(
        private readonly RecordFinancialAuditEntryAction $recordFinancialAudit,
    ) {}

    public function execute(ApproveManualJournalData $data): Journal
    {
        $journal = Journal::with('lines')->findOrFail($data->journalId);

        if ($journal->status !== 'draft') {
            throw new InvalidStateTransitionException(
                "Only a draft journal can be approved — this one is {$journal->status}.",
                ['journal_id' => $journal->id],
            );
        }

        if ($data->approvedByUserId === $journal->posted_by) {
            throw new InvalidStateTransitionException(
                'A manual journal cannot be approved by the same user who created it.',
                ['journal_id' => $journal->id],
            );
        }

        return $this->transaction(function () use ($journal, $data): Journal {
            $journal->update(['status' => 'posted', 'approved_by' => $data->approvedByUserId]);

            $totalDebitBaseMinor = $journal->lines->where('direction', 'DR')->sum('base_amount_minor');

            $this->recordFinancialAudit->execute(new RecordFinancialAuditEntryData(
                schoolId: $journal->school_id,
                eventType: 'manual_journal_approved',
                subjectType: 'journal',
                subjectId: $journal->id,
                causerId: $data->approvedByUserId,
                academicYearId: $journal->academic_year_id,
                payload: [
                    'journal_number' => $journal->journal_number,
                    'posted_by' => $journal->posted_by,
                ],
                amount: Money::of((int) $totalDebitBaseMinor, Currency::from($journal->lines->first()->base_currency)),
                termId: $journal->term_id,
            ));

            event(new ManualJournalApproved($journal));
            event(new JournalPosted($journal));

            return $journal;
        });
    }
}
