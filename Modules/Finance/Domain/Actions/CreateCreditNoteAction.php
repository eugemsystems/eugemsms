<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\DataObjects\CreateCreditNoteData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\Events\CreditNoteIssued;
use Modules\Finance\Domain\Exceptions\CreditNoteRequiresApprovalException;
use Modules\Finance\Models\CreditNote;
use Modules\Finance\Models\CreditNoteLine;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\Invoice;

/**
 * ACT-CreateCreditNote (Book B FIN-03 §4/BR-FIN-03-010/011,
 * AC-FIN-03-004). Posts `Dr Fee Income / Cr Fee Debtors` — the exact
 * reverse of a fee billing entry, never a receipt — so it correctly
 * falls out of every collections total while still reducing the
 * debtor balance. Subledgers the debtor leg to the student rather
 * than a specific guardian: this pass's credit notes are raised at
 * the learner level (e.g. a dropped-subject credit), not yet tied to
 * resolving which of several split-invoice guardians it corrects —
 * that refinement waits for the credit note screen's own liability
 * re-resolution, not built here.
 */
final class CreateCreditNoteAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly PostJournalAction $postJournal,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(CreateCreditNoteData $data): CreditNote
    {
        $totalMinor = array_sum(array_column($data->lines, 'amount_minor'));
        $threshold = (int) $this->settings->get('finance.credit_note_approval_threshold_minor', new ScopeChain(schoolId: $data->schoolId));

        if ($totalMinor > $threshold && $data->approvedByUserId === null) {
            throw CreditNoteRequiresApprovalException::aboveThreshold($totalMinor, $threshold);
        }

        return $this->transaction(function () use ($data, $totalMinor): CreditNote {
            $number = $this->allocateNumber->execute(new AllocateNumberData(
                schoolId: $data->schoolId,
                documentType: 'credit_note',
                allocatedByUserId: $data->raisedByUserId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
            ));

            $creditNote = CreditNote::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'credit_note_number' => $number->formatted_number,
                'student_id' => $data->studentId,
                'invoice_id' => $data->invoiceId,
                'reason_code' => $data->reasonCode,
                'reason' => $data->reason,
                'amount_minor' => $totalMinor,
                'currency' => $data->currency,
                'issue_date' => Carbon::now()->toDateString(),
                'status' => 'issued',
                'raised_by' => $data->raisedByUserId,
                'approved_by' => $data->approvedByUserId,
                'created_at' => Carbon::now(),
            ]);

            $journalLines = [];
            $currency = Currency::from($data->currency);

            foreach ($data->lines as $line) {
                $component = FeeComponent::findOrFail($line['component_id']);
                $amount = Money::of($line['amount_minor'], $currency);

                CreditNoteLine::create([
                    'credit_note_id' => $creditNote->id,
                    'invoice_line_id' => $line['invoice_line_id'] ?? null,
                    'component_id' => $component->id,
                    'description' => $line['description'],
                    'amount_minor' => $line['amount_minor'],
                    'currency' => $data->currency,
                ]);

                $journalLines[] = new JournalLineData(
                    accountId: $component->income_account_id,
                    direction: 'DR',
                    amount: $amount,
                    subledgerType: 'student',
                    subledgerId: $data->studentId,
                    narration: $line['description'],
                );

                $journalLines[] = new JournalLineData(
                    accountId: $component->debtor_account_id,
                    direction: 'CR',
                    amount: $amount,
                    subledgerType: 'student',
                    subledgerId: $data->studentId,
                    narration: $line['description'],
                );
            }

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'CREDIT_NOTE',
                narration: "Credit note {$creditNote->credit_note_number}: {$data->reason}",
                lines: $journalLines,
                effectiveAt: Carbon::now(),
                postedByUserId: $data->raisedByUserId,
                sourceType: 'credit_note',
                sourceId: $creditNote->id,
            ));

            $creditNote->update(['journal_id' => $journal->id]);

            if ($data->invoiceId !== null) {
                $invoice = Invoice::findOrFail($data->invoiceId);
                $newBalance = $invoice->balance_minor - $totalMinor;

                $invoice->update([
                    'credited_minor' => $invoice->credited_minor + $totalMinor,
                    'balance_minor' => $newBalance,
                    'status' => $newBalance <= 0 ? 'paid' : 'partially_paid',
                ]);
            }

            event(new CreditNoteIssued($creditNote));

            return $creditNote->fresh('lines');
        });
    }
}
