<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\ClearChequeAction;
use Modules\Finance\Domain\Actions\CreateFeeComponentAction;
use Modules\Finance\Domain\Actions\CreateReceiptAction;
use Modules\Finance\Domain\Actions\DeclareTillCountAction;
use Modules\Finance\Domain\Actions\OpenTillSessionAction;
use Modules\Finance\Domain\Actions\ResolveSuspenseItemAction;
use Modules\Finance\Domain\Actions\RevealAndCloseTillSessionAction;
use Modules\Finance\Domain\Actions\VoidReceiptAction;
use Modules\Finance\Domain\DataObjects\ClearChequeData;
use Modules\Finance\Domain\DataObjects\CreateFeeComponentData;
use Modules\Finance\Domain\DataObjects\CreateReceiptData;
use Modules\Finance\Domain\DataObjects\DeclareTillCountData;
use Modules\Finance\Domain\DataObjects\OpenTillSessionData;
use Modules\Finance\Domain\DataObjects\ResolveSuspenseItemData;
use Modules\Finance\Domain\DataObjects\RevealAndCloseTillSessionData;
use Modules\Finance\Domain\DataObjects\VoidReceiptData;
use Modules\Finance\Domain\Exceptions\NoOpenTillSessionException;
use Modules\Finance\Domain\Exceptions\TillDeclarationRequiredException;
use Modules\Finance\Domain\Exceptions\TillSessionAlreadyOpenException;
use Modules\Finance\Domain\Exceptions\VarianceSignOffRequiredException;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceLine;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\SuspenseItem;
use Modules\Finance\Models\Till;
use Modules\Finance\Models\TillSession;
use Modules\People\Models\Student;

/**
 * @return array<string, mixed>
 */
function fin04Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $cashier = User::factory()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}',
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'receipt', pattern: 'RCT/{SEQ:6}',
    ));

    $cashAccount = Account::factory()->for($school)->create();
    $bankAccount = Account::factory()->for($school)->create();
    $cashOverShort = Account::factory()->for($school)->create();
    $suspenseAccount = Account::factory()->for($school)->create();
    $creditBalanceAccount = Account::factory()->for($school)->create();
    $unclearedChequeAccount = Account::factory()->for($school)->create();

    $till = Till::factory()->for($school)->create([
        'cash_account_id' => $cashAccount->id,
        'bank_account_id' => $bankAccount->id,
    ]);

    $income = Account::factory()->for($school)->income()->create();
    $debtor = Account::factory()->for($school)->controlAccount('student')->create();
    $tuition = app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
        schoolId: $school->id, code: 'TUITION', name: 'Tuition', category: 'tuition',
        incomeAccountId: $income->id, debtorAccountId: $debtor->id, defaultCurrency: 'USD', createdByUserId: $cashier->id,
    ));

    $student = Student::factory()->for($school)->create();

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'cashier' => $cashier, 'till' => $till,
        'tuition' => $tuition, 'student' => $student, 'cashOverShort' => $cashOverShort,
        'suspenseAccount' => $suspenseAccount, 'creditBalanceAccount' => $creditBalanceAccount,
        'unclearedChequeAccount' => $unclearedChequeAccount,
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function fin04Invoice(array $f, int $netMinor = 30000, int $guardianId = 501): Invoice
{
    $invoice = Invoice::factory()->create([
        'school_id' => $f['school']->id,
        'academic_year_id' => $f['year']->id,
        'term_id' => $f['term']->id,
        'student_id' => $f['student']->id,
        'billed_party_id' => $guardianId,
        'gross_minor' => $netMinor,
        'net_minor' => $netMinor,
        'balance_minor' => $netMinor,
        'currency' => 'USD',
        'due_date' => now()->toDateString(),
    ]);

    InvoiceLine::factory()->create([
        'school_id' => $f['school']->id,
        'invoice_id' => $invoice->id,
        'component_id' => $f['tuition']->id,
        'gross_minor' => $netMinor,
        'net_minor' => $netMinor,
        'currency' => 'USD',
        'allocation_priority' => $f['tuition']->allocation_priority,
        'tax_category' => $f['tuition']->tax_category,
    ]);

    return $invoice->fresh('lines');
}

/**
 * @param  array<string, mixed>  $f
 */
function openTill(array $f): TillSession
{
    return app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillId: $f['till']->id, cashierId: $f['cashier']->id, openingFloat: ['USD' => 5000],
    ));
}

it('refuses to receipt outside an open till session (AC-FIN-04-001)', function (): void {
    $f = fin04Fixture();

    expect(fn () => app(CreateReceiptAction::class)->execute(new CreateReceiptData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillSessionId: 999, receiptType: 'fee', payerType: 'guardian', payerName: 'Walk-in',
        currency: 'USD', tenders: [['tender_type' => 'cash', 'amount_minor' => 5000, 'currency' => 'USD']],
        receivedByUserId: $f['cashier']->id,
    )))->toThrow(NoOpenTillSessionException::class);
});

it('refuses a second open session for the same cashier (BR-FIN-04-002)', function (): void {
    $f = fin04Fixture();
    openTill($f);

    expect(fn () => openTill($f))->toThrow(TillSessionAlreadyOpenException::class);
});

it('allocates a receipt to the oldest invoice, settling it in full, with a real journal (AC-FIN-04-007 baseline)', function (): void {
    $f = fin04Fixture();
    $invoice = fin04Invoice($f, netMinor: 30000);
    $session = openTill($f);

    $receipt = app(CreateReceiptAction::class)->execute(new CreateReceiptData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillSessionId: $session->id, receiptType: 'fee', payerType: 'guardian', payerName: 'Mr Moyo',
        studentId: $f['student']->id, currency: 'USD',
        tenders: [['tender_type' => 'cash', 'amount_minor' => 30000, 'currency' => 'USD']],
        receivedByUserId: $f['cashier']->id,
    ));

    expect($receipt->journal_id)->not->toBeNull()
        ->and((int) $receipt->allocated_minor)->toBe(30000)
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and((int) $invoice->fresh()->balance_minor)->toBe(0);
});

it('creates a credit balance for an overpayment and posts nothing to income (AC-FIN-04-007)', function (): void {
    $f = fin04Fixture();
    fin04Invoice($f, netMinor: 30000);
    $session = openTill($f);

    $receipt = app(CreateReceiptAction::class)->execute(new CreateReceiptData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillSessionId: $session->id, receiptType: 'fee', payerType: 'guardian', payerName: 'Mr Moyo',
        studentId: $f['student']->id, currency: 'USD',
        tenders: [['tender_type' => 'cash', 'amount_minor' => 50000, 'currency' => 'USD']],
        receivedByUserId: $f['cashier']->id, creditBalanceAccountId: $f['creditBalanceAccount']->id,
    ));

    $journal = Journal::with('lines')->find($receipt->journal_id);
    $creditLine = $journal->lines->firstWhere('account_id', $f['creditBalanceAccount']->id);

    expect((int) $receipt->allocated_minor)->toBe(30000)
        ->and((int) $receipt->unallocated_minor)->toBe(20000)
        ->and($creditLine)->not->toBeNull()
        ->and((int) $creditLine->amount_minor)->toBe(20000)
        ->and($journal->lines->firstWhere('account_id', $f['tuition']->income_account_id))->toBeNull();
});

it('receipts an unidentified deposit to suspense and later resolves it against the learner (AC-FIN-04-004/005)', function (): void {
    $f = fin04Fixture();
    $invoice = fin04Invoice($f, netMinor: 30000);
    $session = openTill($f);

    $receipt = app(CreateReceiptAction::class)->execute(new CreateReceiptData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillSessionId: $session->id, receiptType: 'fee', payerType: 'guardian', payerName: 'SCHOOL FEES',
        currency: 'USD', tenders: [['tender_type' => 'bank_transfer', 'amount_minor' => 30000, 'currency' => 'USD']],
        receivedByUserId: $f['cashier']->id, suspenseAccountId: $f['suspenseAccount']->id,
    ));

    expect($receipt->is_suspense)->toBeTrue();
    $journal = Journal::with('lines')->find($receipt->journal_id);
    expect($journal->lines->firstWhere('account_id', $f['suspenseAccount']->id)->direction)->toBe('CR');

    $suspenseItem = SuspenseItem::where('receipt_id', $receipt->id)->firstOrFail();

    $resolved = app(ResolveSuspenseItemAction::class)->execute(new ResolveSuspenseItemData(
        suspenseItemId: $suspenseItem->id, studentId: $f['student']->id, resolvedByUserId: $f['cashier']->id,
        suspenseAccountId: $f['suspenseAccount']->id,
    ));

    expect($resolved->status)->toBe('resolved')
        ->and($resolved->resolved_by)->toBe($f['cashier']->id)
        ->and($invoice->fresh()->status)->toBe('paid');
});

it('voids a receipt by reversing its journal and every allocation, restoring the invoice (AC-FIN-04-008)', function (): void {
    $f = fin04Fixture();
    $invoice = fin04Invoice($f, netMinor: 30000);
    $session = openTill($f);

    $receipt = app(CreateReceiptAction::class)->execute(new CreateReceiptData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillSessionId: $session->id, receiptType: 'fee', payerType: 'guardian', payerName: 'Mr Moyo',
        studentId: $f['student']->id, currency: 'USD',
        tenders: [['tender_type' => 'cash', 'amount_minor' => 30000, 'currency' => 'USD']],
        receivedByUserId: $f['cashier']->id,
    ));

    expect($invoice->fresh()->status)->toBe('paid');

    $voided = app(VoidReceiptAction::class)->execute(new VoidReceiptData($receipt->id, 'Duplicate receipt entered in error', $f['cashier']->id));

    expect($voided->status)->toBe('voided')
        ->and($invoice->fresh()->status)->toBe('issued')
        ->and((int) $invoice->fresh()->balance_minor)->toBe(30000);

    $original = Journal::find($receipt->journal_id);
    expect($original->reversed_by_journal_id)->not->toBeNull();
});

it('does not reduce the learner balance until a cheque clears, then settles automatically (AC-FIN-04-010)', function (): void {
    $f = fin04Fixture();
    $invoice = fin04Invoice($f, netMinor: 40000);
    $session = openTill($f);

    $receipt = app(CreateReceiptAction::class)->execute(new CreateReceiptData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillSessionId: $session->id, receiptType: 'fee', payerType: 'guardian', payerName: 'Mr Moyo',
        studentId: $f['student']->id, currency: 'USD',
        tenders: [['tender_type' => 'cheque', 'amount_minor' => 40000, 'currency' => 'USD', 'reference' => 'CHQ001']],
        receivedByUserId: $f['cashier']->id, unclearedChequeAccountId: $f['unclearedChequeAccount']->id,
    ));

    expect($invoice->fresh()->status)->toBe('issued')
        ->and((int) $invoice->fresh()->balance_minor)->toBe(40000);

    $tender = $receipt->tenders->first();
    expect($tender->is_cleared)->toBeFalse();

    app(ClearChequeAction::class)->execute(new ClearChequeData(
        receiptTenderId: $tender->id, clearedByUserId: $f['cashier']->id, unclearedChequeAccountId: $f['unclearedChequeAccount']->id,
    ));

    expect($invoice->fresh()->status)->toBe('paid')
        ->and((int) $invoice->fresh()->balance_minor)->toBe(0);
});

it('reveals nothing before a declaration exists, then requires sign-off beyond tolerance (AC-FIN-04-002/003)', function (): void {
    $f = fin04Fixture();
    $invoice = fin04Invoice($f, netMinor: 30000);
    $session = openTill($f);

    expect(fn () => app(RevealAndCloseTillSessionAction::class)->execute(new RevealAndCloseTillSessionData(
        tillSessionId: $session->id, closedByUserId: $f['cashier']->id, cashOverShortAccountId: $f['cashOverShort']->id,
    )))->toThrow(TillDeclarationRequiredException::class);

    app(CreateReceiptAction::class)->execute(new CreateReceiptData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillSessionId: $session->id, receiptType: 'fee', payerType: 'guardian', payerName: 'Mr Moyo',
        studentId: $f['student']->id, currency: 'USD',
        tenders: [['tender_type' => 'cash', 'amount_minor' => 30000, 'currency' => 'USD']],
        receivedByUserId: $f['cashier']->id,
    ));

    // Opening float 5000 + 30000 cash received = 35000 expected. Declares
    // 1200 minor units short (USD 12.00), beyond the USD 1.00 tolerance.
    app(DeclareTillCountAction::class)->execute(new DeclareTillCountData(
        tillSessionId: $session->id, declaredClosing: ['USD' => 33800], declaredByUserId: $f['cashier']->id,
    ));

    expect(fn () => app(RevealAndCloseTillSessionAction::class)->execute(new RevealAndCloseTillSessionData(
        tillSessionId: $session->id, closedByUserId: $f['cashier']->id, cashOverShortAccountId: $f['cashOverShort']->id,
    )))->toThrow(VarianceSignOffRequiredException::class);

    $supervisor = User::factory()->create();
    $closed = app(RevealAndCloseTillSessionAction::class)->execute(new RevealAndCloseTillSessionData(
        tillSessionId: $session->id, closedByUserId: $f['cashier']->id, cashOverShortAccountId: $f['cashOverShort']->id,
        varianceReason: 'Cash miscount at close, verified with camera footage', supervisedByUserId: $supervisor->id,
    ));

    expect($closed->status)->toBe('closed')
        ->and($closed->variance['USD'])->toBe(-1200)
        ->and($closed->supervised_by)->toBe($supervisor->id);

    $journal = Journal::with('lines')->find($closed->journal_id);
    expect($journal->lines->firstWhere('account_id', $f['cashOverShort']->id))->not->toBeNull();
});
