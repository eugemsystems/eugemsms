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
use Modules\Finance\Models\AwardDiscountCommitment;
use Modules\Finance\Models\AwardUtilisation;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceLine;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\Finance\Models\LearnerFeeLine;
use Modules\Finance\Models\SchemeBudgetEnvelope;
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
 * subledgered to the guardian / Cr Fee Income per component, each at
 * the line's GROSS — Book K FIN-07 §3 ⭐/BR-FIN-07-001, never net) —
 * BR-FIN-03-003's "an invoice with no journal cannot exist", so both
 * happen in the same transaction. Where a fee line carries a discount
 * (`AwardDiscountCommitment` rows `ComputeBillingRunAction` already
 * wrote), an additional Dr scheme-contra-account / Cr Fee Debtors
 * entry posts per contributing award, and the append-only
 * `AwardUtilisation` row is created here — the first point a
 * `journal_id` actually exists for it. This supersedes `FIN-02`'s own
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

        // BR-FIN-07-001/014: a fee line fully covered by discount
        // (net_minor = 0) never earns a share from resolve() itself —
        // $remaining starts at 0, so no pass ever pushes one — yet the
        // school must still see its gross and discount. Attribute it,
        // as a zero-net share, to the same default-responsible
        // guardian a non-zero line would have fallen through to, so it
        // flows through the same per-guardian invoicing below.
        $sharedLineIds = $shares->pluck('lineId')->unique();
        $zeroNetLines = $assignment->lines->whereNotIn('id', $sharedLineIds)->where('net_minor', 0)->where('discount_minor', '>', 0);

        if ($zeroNetLines->isNotEmpty()) {
            $defaultGuardianId = $this->liabilityResolver->defaultResponsible($assignment->student);

            foreach ($zeroNetLines as $line) {
                $shares->push(new LiabilityShare($defaultGuardianId, $line->id, $line->component_id, 0, $line->currency, null));
            }
        }

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

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $assignment->school_id,
            documentType: 'invoice',
            allocatedByUserId: $data->issuedByUserId,
            academicYearId: $assignment->academic_year_id,
            termId: $assignment->term_id,
        ));

        // First pass: compute every line's gross/discount/net share with
        // no side effects yet. `invoices.gross_minor`/`discount_minor`/
        // `net_minor` are NOT in `Invoice::MUTABLE_AFTER_CREATE` (BR-FIN-03-004
        // — an issued invoice is never edited), so the totals must be
        // known before `Invoice::create()` runs, not patched in after.
        $lineComputations = [];
        $invoiceGrossMinor = 0;
        $invoiceDiscountMinor = 0;
        $invoiceNetMinor = 0;

        foreach ($shares->groupBy('lineId') as $lineId => $sharesForLine) {
            $feeLine = $lineById->get($lineId);
            $netShareMinor = $sharesForLine->sum('shareMinor');

            // BR-FIN-07-001 ⭐: gross is never reduced. A fee line split
            // across guardians (a rare feature independent of FIN-07)
            // shares its gross/discount proportionally to each
            // guardian's own net share — exact in the single-guardian
            // case (by far the common one), a documented rounding
            // approximation in the split case, the same category of
            // approximation FIN-04's own multi-receipt allocation
            // already accepts.
            if ($feeLine->net_minor > 0) {
                $ratio = bcdiv((string) $netShareMinor, (string) $feeLine->net_minor, 10);
                $grossShareMinor = (int) round((float) bcmul((string) $feeLine->gross_minor, $ratio, 10));
                $discountShareMinor = $grossShareMinor - $netShareMinor;
            } else {
                // The line nets to zero (e.g. a 100%-discounted award).
                // resolve() itself never produces a share for it — this
                // is exactly the single synthetic zero-net share
                // execute() injected above for the default-responsible
                // guardian, so that one guardian carries the line's
                // full gross/discount even though their net share is 0.
                $grossShareMinor = $feeLine->gross_minor;
                $discountShareMinor = $grossShareMinor;
            }

            $lineComputations[] = [
                'feeLine' => $feeLine,
                'grossShareMinor' => $grossShareMinor,
                'discountShareMinor' => $discountShareMinor,
                'netShareMinor' => $netShareMinor,
            ];

            $invoiceGrossMinor += $grossShareMinor;
            $invoiceDiscountMinor += $discountShareMinor;
            $invoiceNetMinor += $netShareMinor;
        }

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
            'gross_minor' => $invoiceGrossMinor,
            'discount_minor' => $invoiceDiscountMinor,
            'net_minor' => $invoiceNetMinor,
            'balance_minor' => $invoiceNetMinor,
            'currency' => $first->currency,
            'status' => 'issued',
            'created_by' => $data->issuedByUserId,
        ]);

        $journalLines = [];
        $lineNumber = 1;

        /** @var array<int, array{fee_line_id: int, award_id: int, term_id: int, component_id: int, discount_minor: int, currency: string}> $pendingUtilisation */
        $pendingUtilisation = [];

        foreach ($lineComputations as ['feeLine' => $feeLine, 'grossShareMinor' => $grossShareMinor, 'discountShareMinor' => $discountShareMinor, 'netShareMinor' => $netShareMinor]) {
            $component = $components->get($feeLine->component_id);

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
                'gross_minor' => $grossShareMinor,
                'discount_minor' => $discountShareMinor,
                'net_minor' => $netShareMinor,
                'currency' => $first->currency,
                'allocation_priority' => $component->allocation_priority,
                'tax_category' => $component->tax_category,
                'is_fiscalisable' => $component->is_fiscalisable,
            ]);

            $journalLines[] = new JournalLineData(
                accountId: $component->debtor_account_id,
                direction: 'DR',
                amount: Money::of($grossShareMinor, $currency),
                subledgerType: 'guardian',
                subledgerId: $first->guardianId,
                narration: $feeLine->calculation_note,
            );

            $journalLines[] = new JournalLineData(
                accountId: $component->income_account_id,
                direction: 'CR',
                amount: Money::of($grossShareMinor, $currency),
                narration: $feeLine->calculation_note,
            );

            if ($discountShareMinor <= 0) {
                continue;
            }

            $commitments = AwardDiscountCommitment::where('fee_line_id', $feeLine->id)->with('scheme')->get();
            $lineDiscountTotal = max(1, (int) $commitments->sum('discount_minor'));

            foreach ($commitments as $commitment) {
                $commitmentShareMinor = (int) round($discountShareMinor * ($commitment->discount_minor / $lineDiscountTotal));

                if ($commitmentShareMinor <= 0) {
                    continue;
                }

                $journalLines[] = new JournalLineData(
                    accountId: $commitment->scheme->contra_account_id,
                    direction: 'DR',
                    amount: Money::of($commitmentShareMinor, $currency),
                    narration: "Discount — {$commitment->scheme->name}",
                );

                $journalLines[] = new JournalLineData(
                    accountId: $component->debtor_account_id,
                    direction: 'CR',
                    amount: Money::of($commitmentShareMinor, $currency),
                    subledgerType: 'guardian',
                    subledgerId: $first->guardianId,
                    narration: "Discount — {$commitment->scheme->name}",
                );

                // The discount moves from "committed" (booked when
                // AwardDiscountResolver accepted it at billing-preview
                // time) to "utilised" (now that it's actually posted)
                // — never double-counted against the envelope.
                $envelope = SchemeBudgetEnvelope::where('school_id', $assignment->school_id)
                    ->where('scheme_id', $commitment->scheme_id)
                    ->where('academic_year_id', $assignment->academic_year_id)
                    ->first();

                $envelope?->update([
                    'committed_minor' => max(0, $envelope->committed_minor - $commitmentShareMinor),
                    'utilised_minor' => $envelope->utilised_minor + $commitmentShareMinor,
                ]);

                $pendingUtilisation[] = [
                    'fee_line_id' => $feeLine->id,
                    'award_id' => $commitment->award_id,
                    'term_id' => $assignment->term_id,
                    'component_id' => $component->id,
                    'discount_minor' => $commitmentShareMinor,
                    'currency' => $first->currency,
                ];
            }
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

        $postedAt = Carbon::now();

        foreach ($pendingUtilisation as $utilisation) {
            AwardUtilisation::create([
                'school_id' => $assignment->school_id,
                ...$utilisation,
                'journal_id' => $journal->id,
                'posted_at' => $postedAt,
            ]);
        }

        event(new InvoiceIssued($invoice));

        return $invoice->fresh('lines');
    }
}
