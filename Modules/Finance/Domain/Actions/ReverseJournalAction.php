<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordFinancialAuditEntryAction;
use Modules\Core\Domain\DataObjects\Audit\RecordFinancialAuditEntryData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\DataObjects\ReverseJournalData;
use Modules\Finance\Domain\Events\JournalReversed;
use Modules\Finance\Domain\Exceptions\JournalAlreadyReversedException;
use Modules\Finance\Domain\Support\JournalAssembler;
use Modules\Finance\Models\Journal;

/**
 * ACT-ReverseJournal (Book B FIN-01 §5/BR-FIN-01-013..015). Correction
 * by reversal only — the original journal's rows never change (its
 * `journal_lines` can't; `journals` permits touching only
 * `reversed_by_journal_id` here). The mirror carries every line with
 * direction flipped, which is why it always balances without a second
 * assertion — flipping DR/CR preserves the per-currency equality the
 * original journal already proved.
 */
final class ReverseJournalAction extends Action
{
    public function __construct(
        private readonly JournalAssembler $assembler,
        private readonly RecordFinancialAuditEntryAction $recordFinancialAudit,
    ) {}

    public function execute(ReverseJournalData $data): Journal
    {
        if (mb_strlen($data->reason) < 15) {
            throw ValidationException::withMessages([
                'reason' => 'A reversal reason of at least 15 characters is required.',
            ]);
        }

        $original = Journal::with('lines')->findOrFail($data->journalId);

        if ($original->isReversed()) {
            throw JournalAlreadyReversedException::forJournal($original->id);
        }

        return $this->transaction(function () use ($original, $data): Journal {
            $mirrorLines = $original->lines->map(fn ($line): JournalLineData => new JournalLineData(
                accountId: $line->account_id,
                direction: $line->direction === 'DR' ? 'CR' : 'DR',
                amount: Money::of($line->amount_minor, Currency::from($line->currency)),
                costCentreId: $line->cost_centre_id,
                subledgerType: $line->subledger_type,
                subledgerId: $line->subledger_id,
                narration: $line->narration,
            ))->all();

            $reversal = $this->assembler->assemble(new PostJournalData(
                schoolId: $original->school_id,
                academicYearId: $original->academic_year_id,
                termId: $original->term_id,
                journalType: 'REVERSAL',
                narration: "Reversal of {$original->journal_number}: {$data->reason}",
                lines: $mirrorLines,
                effectiveAt: Carbon::parse($original->effective_at),
                postedByUserId: $data->reversedByUserId,
                sourceType: 'journal',
                sourceId: $original->id,
                overrideSoftClose: $data->overrideCrossPeriod,
                isReversal: true,
                reversesJournalId: $original->id,
                reversalReason: $data->reason,
            ), 'posted');

            $original->update(['reversed_by_journal_id' => $reversal->id]);

            $totalDebitBaseMinor = $reversal->lines->where('direction', 'DR')->sum('base_amount_minor');

            $this->recordFinancialAudit->execute(new RecordFinancialAuditEntryData(
                schoolId: $original->school_id,
                eventType: 'journal_reversed',
                subjectType: 'journal',
                subjectId: $reversal->id,
                causerId: $data->reversedByUserId,
                academicYearId: $original->academic_year_id,
                payload: [
                    'original_journal_id' => $original->id,
                    'reason' => $data->reason,
                ],
                amount: Money::of((int) $totalDebitBaseMinor, Currency::from($reversal->lines->first()->base_currency)),
                termId: $original->term_id,
            ));

            event(new JournalReversed($original->fresh(), $reversal));

            return $reversal;
        });
    }
}
