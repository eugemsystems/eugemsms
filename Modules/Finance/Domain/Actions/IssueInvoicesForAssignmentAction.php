<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\DataObjects\IssueInvoicesForAssignmentData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\Events\InvoiceIssued;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceLine;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\Finance\Models\LearnerFeeLine;
use Modules\People\Domain\DataObjects\LiabilityLineInput;
use Modules\People\Domain\DataObjects\LiabilityShare;
use Modules\People\Domain\Support\LiabilityResolver;

/**
 * ACT-IssueInvoicesForAssignment (Book B FIN-03 §3/§4 ⭐/BR-FIN-03-001/
 * 003/006). Resolves who pays what via `PPL-03`'s `LiabilityResolver`
 * and raises one invoice per (billed party, currency) pair — the
 * spec's own worked example groups a party's USD and ZWG amounts
 * under one invoice number in prose, but `invoices.currency` is a
 * single NOT NULL column, so this pass issues a separate invoice per
 * currency rather than inventing a multi-currency invoice shape the
 * schema doesn't have. `billed_party_type` is always `'guardian'` —
 * `sponsor`/`employer`/`organisation` billed parties are still
 * `Guardian` rows (`guardian_type = 'organisation'`) in this pass;
 * distinguishing them is a screens/reporting concern, not a
 * resolution one.
 *
 * Posts one `FEE_BILLING` journal per invoice (Dr Fee Debtors,
 * subledgered to the guardian / Cr Fee Income per component) — BR-FIN-03-003's
 * "an invoice with no journal cannot exist", so both happen in the
 * same transaction. This supersedes `FIN-02`'s own
 * `CommitBillingRunAction`, which posted a single student-subledgered
 * journal per learner before this action existed; that action now
 * delegates here instead of posting its own journal.
 */
final class IssueInvoicesForAssignmentAction extends Action
{
    public function __construct(
        private readonly LiabilityResolver $liabilityResolver,
        private readonly AllocateNumberAction $allocateNumber,
        private readonly PostJournalAction $postJournal,
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return Collection<int, Invoice>
     */
    public function execute(IssueInvoicesForAssignmentData $data): Collection
    {
        $assignment = LearnerFeeAssignment::with('lines', 'student')->findOrFail($data->assignmentId);

        $lineInputs = $assignment->lines->map(fn ($line): LiabilityLineInput => new LiabilityLineInput(
            lineId: $line->id,
            componentId: $line->component_id,
            netMinor: $line->net_minor,
            currency: $line->currency,
        ));

        $shares = $this->liabilityResolver->resolve($assignment->student, $lineInputs, Carbon::parse($assignment->computed_at));

        $dueDays = (int) $this->settings->get('finance.invoice_due_days_after_issue', new ScopeChain(schoolId: $assignment->school_id));
        $lineById = $assignment->lines->keyBy('id');
        $components = FeeComponent::query()->whereIn('id', $assignment->lines->pluck('component_id'))->get()->keyBy('id');

        return $this->transaction(function () use ($assignment, $shares, $dueDays, $lineById, $components, $data): Collection {
            $invoices = collect();

            foreach ($shares->groupBy(fn ($share) => "{$share->guardianId}:{$share->currency}") as $group) {
                $invoices->push($this->issueOneInvoice($assignment, $group, $dueDays, $lineById, $components, $data));
            }

            $assignment->update(['status' => 'invoiced']);

            return $invoices;
        });
    }

    /**
     * @param  Collection<int, LiabilityShare>  $shares
     * @param  Collection<int, LearnerFeeLine>  $lineById
     * @param  Collection<int, FeeComponent>  $components
     */
    private function issueOneInvoice(LearnerFeeAssignment $assignment, Collection $shares, int $dueDays, Collection $lineById, Collection $components, IssueInvoicesForAssignmentData $data): Invoice
    {
        $first = $shares->first();
        $currency = Currency::from($first->currency);
        $grossMinor = $shares->sum('shareMinor');

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $assignment->school_id,
            documentType: 'invoice',
            allocatedByUserId: $data->issuedByUserId,
            academicYearId: $assignment->academic_year_id,
            termId: $assignment->term_id,
        ));

        $invoice = Invoice::create([
            'school_id' => $assignment->school_id,
            'academic_year_id' => $assignment->academic_year_id,
            'term_id' => $assignment->term_id,
            'invoice_number' => $number->formatted_number,
            'invoice_type' => 'term',
            'student_id' => $assignment->student_id,
            'billed_party_type' => 'guardian',
            'billed_party_id' => $first->guardianId,
            'assignment_id' => $assignment->id,
            'issue_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays($dueDays)->toDateString(),
            'gross_minor' => $grossMinor,
            'net_minor' => $grossMinor,
            'balance_minor' => $grossMinor,
            'currency' => $first->currency,
            'status' => 'issued',
            'created_by' => $data->issuedByUserId,
        ]);

        $journalLines = [];
        $lineNumber = 1;

        foreach ($shares->groupBy('lineId') as $lineId => $sharesForLine) {
            $feeLine = $lineById->get($lineId);
            $component = $components->get($feeLine->component_id);
            $shareMinor = $sharesForLine->sum('shareMinor');

            InvoiceLine::create([
                'school_id' => $assignment->school_id,
                'invoice_id' => $invoice->id,
                'line_number' => $lineNumber++,
                'component_id' => $component->id,
                'fee_line_id' => $feeLine->id,
                'description' => $component->name,
                'calculation_note' => $feeLine->calculation_note,
                'quantity' => $feeLine->quantity,
                'unit_rate_minor' => $feeLine->unit_rate_minor,
                'gross_minor' => $shareMinor,
                'net_minor' => $shareMinor,
                'currency' => $first->currency,
                'allocation_priority' => $component->allocation_priority,
                'tax_category' => $component->tax_category,
                'is_fiscalisable' => $component->is_fiscalisable,
            ]);

            $amount = Money::of($shareMinor, $currency);

            $journalLines[] = new JournalLineData(
                accountId: $component->debtor_account_id,
                direction: 'DR',
                amount: $amount,
                subledgerType: 'guardian',
                subledgerId: $first->guardianId,
                narration: $feeLine->calculation_note,
            );

            $journalLines[] = new JournalLineData(
                accountId: $component->income_account_id,
                direction: 'CR',
                amount: $amount,
                narration: $feeLine->calculation_note,
            );
        }

        $journal = $this->postJournal->execute(new PostJournalData(
            schoolId: $assignment->school_id,
            academicYearId: $assignment->academic_year_id,
            termId: $assignment->term_id,
            journalType: 'FEE_BILLING',
            narration: "Invoice {$invoice->invoice_number}",
            lines: $journalLines,
            effectiveAt: $data->effectiveAt ?? Carbon::now(),
            postedByUserId: $data->issuedByUserId,
            batchUuid: $data->batchUuid,
            sourceType: 'invoice',
            sourceId: $invoice->id,
        ));

        $invoice->update(['journal_id' => $journal->id]);

        event(new InvoiceIssued($invoice));

        return $invoice->fresh('lines');
    }
}
