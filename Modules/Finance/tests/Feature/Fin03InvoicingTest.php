<?php

use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\Actions\ActivateFeeStructureAction;
use Modules\Finance\Domain\Actions\ApproveBillingRunAction;
use Modules\Finance\Domain\Actions\ComputeBillingRunAction;
use Modules\Finance\Domain\Actions\CreateCreditNoteAction;
use Modules\Finance\Domain\Actions\CreateFeeComponentAction;
use Modules\Finance\Domain\Actions\CreateFeeStructureAction;
use Modules\Finance\Domain\Actions\IssueInvoicesForAssignmentAction;
use Modules\Finance\Domain\Actions\VoidInvoiceAction;
use Modules\Finance\Domain\DataObjects\ActivateFeeStructureData;
use Modules\Finance\Domain\DataObjects\ApproveBillingRunData;
use Modules\Finance\Domain\DataObjects\ComputeBillingRunData;
use Modules\Finance\Domain\DataObjects\CreateCreditNoteData;
use Modules\Finance\Domain\DataObjects\CreateFeeComponentData;
use Modules\Finance\Domain\DataObjects\CreateFeeStructureData;
use Modules\Finance\Domain\DataObjects\IssueInvoicesForAssignmentData;
use Modules\Finance\Domain\DataObjects\VoidInvoiceData;
use Modules\Finance\Domain\Exceptions\InvoiceVoidRefusedException;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\People\Domain\Actions\CreateFeeLiabilityAction;
use Modules\People\Domain\Actions\LinkGuardianToStudentAction;
use Modules\People\Domain\DataObjects\CreateFeeLiabilityData;
use Modules\People\Domain\DataObjects\LinkGuardianToStudentData;
use Modules\People\Models\Guardian;

it('issues one invoice per billed party whose total equals the learner\'s charges, each party seeing only its own (AC-FIN-03-001/BR-FIN-03-006)', function (): void {
    $f = fin02Fixture();

    $boarding = app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
        schoolId: $f['school']->id, code: 'BOARDING', name: 'Boarding', category: 'boarding',
        incomeAccountId: $f['tuition']->income_account_id, debtorAccountId: $f['tuition']->debtor_account_id,
        defaultCurrency: 'USD', createdByUserId: $f['user']->id,
    ));

    $structure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Full-Time Structure', priority: 10,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'FULL_TIME']],
        items: [
            ['component_id' => $f['tuition']->id, 'billing_basis' => 'flat_per_term', 'currency' => 'USD', 'amount_minor' => 45000],
            ['component_id' => $boarding->id, 'billing_basis' => 'flat_per_term', 'currency' => 'USD', 'amount_minor' => 60000],
        ],
        createdByUserId: $f['user']->id,
    ));
    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($structure->id, $f['user']->id));

    $student = fin02Student($f);

    // fin02Student() already links a fee-responsible father — add the
    // employer as the boarding-specific payer, taking priority over him.
    $employer = Guardian::factory()->for($f['school'])->organisation()->create();
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData($student->id, $employer->id, 'employer', $f['user']->id));
    app(CreateFeeLiabilityAction::class)->execute(new CreateFeeLiabilityData(
        $f['school']->id, $student->id, $employer->id, 'full_component', $f['user']->id, componentId: $boarding->id, priority: 10,
    ));

    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData($f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id]));
    app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($run->id, $f['user']->id));

    $assignment = LearnerFeeAssignment::where('student_id', $student->id)->first();
    $invoices = app(IssueInvoicesForAssignmentAction::class)->execute(new IssueInvoicesForAssignmentData($assignment->id, $f['user']->id));

    expect($invoices)->toHaveCount(2);

    $byParty = $invoices->keyBy('billed_party_id');
    expect((int) $byParty[$employer->id]->net_minor)->toBe(60000);

    $fatherInvoice = $invoices->first(fn ($inv) => $inv->billed_party_id !== $employer->id);
    expect((int) $fatherInvoice->net_minor)->toBe(45000);

    expect((int) $invoices->sum('net_minor'))->toBe(105000);
});

it('gives every invoice a journal, and never edits an issued invoice directly (BR-FIN-03-003/004)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f);
    $student = fin02Student($f);

    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData($f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id]));
    app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($run->id, $f['user']->id));

    $assignment = LearnerFeeAssignment::where('student_id', $student->id)->first();
    $invoice = app(IssueInvoicesForAssignmentAction::class)->execute(new IssueInvoicesForAssignmentData($assignment->id, $f['user']->id))->first();

    expect($invoice->journal_id)->not->toBeNull();
    expect(fn () => $invoice->update(['gross_minor' => 1]))->toThrow(InvalidStateTransitionException::class);
});

it('refuses to void an invoice with an allocated payment (AC-FIN-03-002)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f);
    $student = fin02Student($f);

    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData($f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id]));
    app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($run->id, $f['user']->id));
    $assignment = LearnerFeeAssignment::where('student_id', $student->id)->first();
    $invoice = app(IssueInvoicesForAssignmentAction::class)->execute(new IssueInvoicesForAssignmentData($assignment->id, $f['user']->id))->first();

    $invoice->update(['paid_minor' => 5000]);

    expect(fn () => app(VoidInvoiceAction::class)->execute(new VoidInvoiceData($invoice->id, 'Attempting to void a settled invoice', $f['user']->id)))
        ->toThrow(InvoiceVoidRefusedException::class);
});

it('voids an unpaid invoice by reversing its journal, leaving the original untouched, and links a replacement (AC-FIN-03-003)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f);
    $student = fin02Student($f);

    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData($f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id]));
    app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($run->id, $f['user']->id));
    $assignment = LearnerFeeAssignment::where('student_id', $student->id)->first();
    $invoice = app(IssueInvoicesForAssignmentAction::class)->execute(new IssueInvoicesForAssignmentData($assignment->id, $f['user']->id))->first();
    $originalJournalId = $invoice->journal_id;
    $originalNet = $invoice->net_minor;

    $replacement = Invoice::factory()->for($f['school'])->create(['student_id' => $student->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id]);

    $voided = app(VoidInvoiceAction::class)->execute(new VoidInvoiceData($invoice->id, 'Billing error — reissuing with corrected amount', $f['user']->id, replacementInvoiceId: $replacement->id));

    expect($voided->status)->toBe('voided')
        ->and($voided->replaced_by_invoice_id)->toBe($replacement->id)
        ->and((int) $voided->net_minor)->toBe($originalNet)
        ->and($voided->journal_id)->toBe($originalJournalId);

    $reversal = Journal::where('reverses_journal_id', $originalJournalId)->first();
    expect($reversal)->not->toBeNull();

    $original = Journal::find($originalJournalId);
    expect($original->reversed_by_journal_id)->toBe($reversal->id);
});

it('posts a credit note as Dr Fee Income / Cr Fee Debtors, never as a receipt (AC-FIN-03-004/BR-FIN-03-011)', function (): void {
    $f = fin02Fixture();
    $student = fin02Student($f);

    $creditNote = app(CreateCreditNoteAction::class)->execute(new CreateCreditNoteData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
        reasonCode: 'subject_dropped', reason: 'Statistics dropped in week 4', currency: 'USD',
        lines: [['component_id' => $f['tuition']->id, 'description' => 'Statistics credit', 'amount_minor' => 6231]],
        raisedByUserId: $f['user']->id, approvedByUserId: $f['user']->id,
    ));

    $journal = Journal::with('lines')->find($creditNote->journal_id);

    expect($journal->journal_type)->toBe('CREDIT_NOTE')
        ->and($journal->lines->firstWhere('account_id', $f['tuition']->income_account_id)->direction)->toBe('DR')
        ->and($journal->lines->firstWhere('account_id', $f['tuition']->debtor_account_id)->direction)->toBe('CR');

    $feeBillingTotal = Journal::where('journal_type', 'FEE_BILLING')->count();
    expect($feeBillingTotal)->toBe(0);
});
