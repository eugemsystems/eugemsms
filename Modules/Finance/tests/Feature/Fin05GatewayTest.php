<?php

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\InsufficientBalanceException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\ConvertBankLineToSuspenseAction;
use Modules\Finance\Domain\Actions\CreateFeeComponentAction;
use Modules\Finance\Domain\Actions\ImportBankStatementAction;
use Modules\Finance\Domain\Actions\IngestGatewayWebhookAction;
use Modules\Finance\Domain\Actions\InitiatePaymentAction;
use Modules\Finance\Domain\Actions\PollPendingIntentAction;
use Modules\Finance\Domain\Actions\RefundPaymentAction;
use Modules\Finance\Domain\Actions\RegisterPaymentGatewayAction;
use Modules\Finance\Domain\Actions\RunReconciliationAction;
use Modules\Finance\Domain\DataObjects\ConvertBankLineToSuspenseData;
use Modules\Finance\Domain\DataObjects\CreateFeeComponentData;
use Modules\Finance\Domain\DataObjects\ImportBankStatementData;
use Modules\Finance\Domain\DataObjects\IngestGatewayWebhookData;
use Modules\Finance\Domain\DataObjects\InitiatePaymentData;
use Modules\Finance\Domain\DataObjects\PollPendingIntentData;
use Modules\Finance\Domain\DataObjects\RefundPaymentData;
use Modules\Finance\Domain\DataObjects\RegisterPaymentGatewayData;
use Modules\Finance\Domain\DataObjects\RunReconciliationData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Models\GatewayWebhook;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceLine;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\PaymentIntent;
use Modules\Finance\Models\Receipt;
use Modules\Finance\Models\SuspenseItem;
use Modules\People\Models\Student;

/**
 * @return array<string, mixed>
 */
function fin05Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $user = User::factory()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}',
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'receipt', pattern: 'RCT/{SEQ:6}',
    ));

    $income = Account::factory()->for($school)->income()->create();
    $debtor = Account::factory()->for($school)->controlAccount('student')->create();
    $tuition = app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
        schoolId: $school->id, code: 'TUITION', name: 'Tuition', category: 'tuition',
        incomeAccountId: $income->id, debtorAccountId: $debtor->id, defaultCurrency: 'USD', createdByUserId: $user->id,
    ));

    $settlement = Account::factory()->for($school)->create();
    $feeAccount = Account::factory()->for($school)->create();
    $creditBalanceAccount = Account::factory()->for($school)->liability()->create();
    $suspenseAccount = Account::factory()->for($school)->create();

    $gateway = app(RegisterPaymentGatewayAction::class)->execute(new RegisterPaymentGatewayData(
        schoolId: $school->id, driver: 'fake', name: 'Fake Gateway', credentials: 'secret-key',
        supportedMethods: ['ecocash'], supportedCurrencies: ['USD'],
        settlementAccountId: $settlement->id, feeAccountId: $feeAccount->id, isActive: true,
    ));

    $student = Student::factory()->for($school)->create();

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'user' => $user, 'tuition' => $tuition,
        'gateway' => $gateway, 'settlement' => $settlement, 'feeAccount' => $feeAccount,
        'creditBalanceAccount' => $creditBalanceAccount, 'suspenseAccount' => $suspenseAccount, 'student' => $student,
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function fin05Invoice(array $f, int $netMinor = 30000): Invoice
{
    $invoice = Invoice::factory()->create([
        'school_id' => $f['school']->id,
        'academic_year_id' => $f['year']->id,
        'term_id' => $f['term']->id,
        'student_id' => $f['student']->id,
        'billed_party_id' => 501,
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
function fin05Intent(array $f, int $amountMinor = 30000): PaymentIntent
{
    return app(InitiatePaymentAction::class)->execute(new InitiatePaymentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        gatewayId: $f['gateway']->id, idempotencyKey: (string) Str::uuid(), payerName: 'Mrs Moyo',
        purpose: 'fees', amountMinor: $amountMinor, currency: 'USD', studentId: $f['student']->id,
    ));
}

it('returns the original intent when the same idempotency key repeats (AC-FIN-05-001)', function (): void {
    $f = fin05Fixture();
    $key = (string) Str::uuid();

    $first = app(InitiatePaymentAction::class)->execute(new InitiatePaymentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        gatewayId: $f['gateway']->id, idempotencyKey: $key, payerName: 'Mrs Moyo',
        purpose: 'fees', amountMinor: 30000, currency: 'USD', studentId: $f['student']->id,
    ));

    $second = app(InitiatePaymentAction::class)->execute(new InitiatePaymentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        gatewayId: $f['gateway']->id, idempotencyKey: $key, payerName: 'Mrs Moyo',
        purpose: 'fees', amountMinor: 30000, currency: 'USD', studentId: $f['student']->id,
    ));

    expect($second->id)->toBe($first->id)
        ->and(PaymentIntent::where('idempotency_key', $key)->count())->toBe(1);
});

it('stores and flags an invalid webhook signature without creating a receipt (AC-FIN-05-002)', function (): void {
    $f = fin05Fixture();
    $intent = fin05Intent($f);

    $body = json_encode(['gateway_reference' => $intent->gateway_reference, 'status' => 'succeeded', 'amount_minor' => 30000, 'currency' => 'USD', 'signature' => 'tampered']);

    $webhook = app(IngestGatewayWebhookAction::class)->execute(new IngestGatewayWebhookData(
        driver: 'fake', headers: [], body: $body, processedByUserId: $f['user']->id,
    ));

    expect($webhook->signature_valid)->toBeFalse()
        ->and($webhook->processing_status)->toBe('failed')
        ->and($intent->fresh()->status)->not->toBe('succeeded')
        ->and(Receipt::count())->toBe(0);
});

it('creates exactly one receipt when the same settlement webhook is delivered five times (AC-FIN-05-003)', function (): void {
    $f = fin05Fixture();
    fin05Invoice($f, netMinor: 30000);
    $intent = fin05Intent($f);

    $body = json_encode(['gateway_reference' => $intent->gateway_reference, 'status' => 'succeeded', 'amount_minor' => 30000, 'currency' => 'USD', 'signature' => 'valid']);

    for ($i = 0; $i < 5; $i++) {
        app(IngestGatewayWebhookAction::class)->execute(new IngestGatewayWebhookData(
            driver: 'fake', headers: [], body: $body, processedByUserId: $f['user']->id,
            creditBalanceAccountId: $f['creditBalanceAccount']->id,
        ));
    }

    expect(GatewayWebhook::count())->toBe(1)
        ->and(Receipt::count())->toBe(1)
        ->and($intent->fresh()->status)->toBe('succeeded');
});

it('settles a pending intent within the polling fallback when no webhook arrives (AC-FIN-05-004)', function (): void {
    $f = fin05Fixture();
    fin05Invoice($f, netMinor: 30000);
    $intent = fin05Intent($f);
    $intent->update(['metadata' => ['simulated_status' => 'succeeded']]);

    $settled = app(PollPendingIntentAction::class)->execute(new PollPendingIntentData(
        paymentIntentId: $intent->id, processedByUserId: $f['user']->id,
    ));

    expect($settled->status)->toBe('succeeded')
        ->and($settled->receipt_id)->not->toBeNull()
        ->and(Receipt::count())->toBe(1);
});

it('credits the learner the gross, expenses the gateway fee, and nets the bank debit, balanced (AC-FIN-05-005)', function (): void {
    $f = fin05Fixture();
    fin05Invoice($f, netMinor: 50000);
    $intent = fin05Intent($f, amountMinor: 50000);
    $intent->update(['metadata' => ['simulated_status' => 'succeeded', 'simulated_fee_minor' => 1250]]);

    $settled = app(PollPendingIntentAction::class)->execute(new PollPendingIntentData(
        paymentIntentId: $intent->id, processedByUserId: $f['user']->id,
    ));

    $receipt = Receipt::find($settled->receipt_id);
    $journal = Journal::with('lines')->find($receipt->journal_id);

    $bankLine = $journal->lines->firstWhere('account_id', $f['settlement']->id);
    $feeLine = $journal->lines->where('account_id', $f['feeAccount']->id)->firstWhere('direction', 'DR');
    $debtorLine = $journal->lines->firstWhere('account_id', $f['tuition']->debtor_account_id);

    expect((int) $debtorLine->amount_minor)->toBe(50000)
        ->and((int) $feeLine->amount_minor)->toBe(1250)
        ->and($journal->lines->where('account_id', $f['settlement']->id)->sum(fn ($l) => $l->direction === 'DR' ? $l->amount_minor : -$l->amount_minor))->toBe(48750)
        ->and($journal->lines->where('direction', 'DR')->sum('amount_minor'))->toBe($journal->lines->where('direction', 'CR')->sum('amount_minor'));
});

it('converts an unmatched bank credit into a suspense item (AC-FIN-05-006)', function (): void {
    $f = fin05Fixture();

    $glAccount = Account::factory()->for($f['school'])->create();
    $bankAccount = BankAccount::factory()->for($f['school'])->create(['gl_account_id' => $glAccount->id]);

    $statement = app(ImportBankStatementAction::class)->execute(new ImportBankStatementData(
        schoolId: $f['school']->id, bankAccountId: $bankAccount->id,
        statementFrom: now()->subDays(7)->toDateString(), statementTo: now()->toDateString(),
        openingBalanceMinor: 0, closingBalanceMinor: 15000, currency: 'USD',
        lines: [['transaction_date' => now()->toDateString(), 'description' => 'MOBILE MONEY DEPOSIT', 'credit_minor' => 15000]],
        importedByUserId: $f['user']->id,
    ));

    $line = $statement->lines->first();
    expect($line->match_status)->toBe('unmatched');

    $receipt = app(ConvertBankLineToSuspenseAction::class)->execute(new ConvertBankLineToSuspenseData(
        bankStatementLineId: $line->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        convertedByUserId: $f['user']->id, suspenseAccountId: $f['suspenseAccount']->id,
    ));

    expect($receipt->is_suspense)->toBeTrue()
        ->and(SuspenseItem::where('receipt_id', $receipt->id)->exists())->toBeTrue()
        ->and($line->fresh()->match_status)->toBe('manually_matched');
});

it('lists reconciliation exceptions without auto-resolving them (AC-FIN-05-007)', function (): void {
    $f = fin05Fixture();
    fin05Invoice($f, netMinor: 30000);
    $intentA = fin05Intent($f, amountMinor: 30000);
    $intentA->update(['metadata' => ['simulated_status' => 'succeeded']]);
    app(PollPendingIntentAction::class)->execute(new PollPendingIntentData($intentA->id, $f['user']->id));

    $unreportedIntent = fin05Intent($f, amountMinor: 20000);
    $unreportedIntent->update(['status' => 'succeeded', 'completed_at' => now(), 'gateway_reference' => 'FAKE-UNREPORTED']);

    $run = app(RunReconciliationAction::class)->execute(new RunReconciliationData(
        schoolId: $f['school']->id, runDate: now(), scope: 'gateway', currency: 'USD',
        gatewaySettlements: [
            ['gateway_reference' => $intentA->fresh()->gateway_reference, 'amount_minor' => 25000],
        ],
        gatewayId: $f['gateway']->id,
    ));

    $classes = collect($run->exceptions)->pluck('class');

    expect($run->status)->toBe('exceptions')
        ->and($classes)->toContain('AMOUNT_MISMATCH')
        ->and($classes)->toContain('RECEIPT_NO_GATEWAY')
        ->and($run->reviewed_at)->toBeNull();
});

it('hides a gateway from availability once its health check reports down (AC-FIN-05-008)', function (): void {
    $f = fin05Fixture();

    expect($f['gateway']->isAvailable())->toBeTrue();

    $f['gateway']->update(['health_status' => 'down']);

    expect($f['gateway']->fresh()->isAvailable())->toBeFalse();
});

it('never exposes gateway credentials in array or JSON output (AC-FIN-05-009)', function (): void {
    $f = fin05Fixture();

    expect($f['gateway']->toArray())->not->toHaveKey('credentials')
        ->and($f['gateway']->credentials)->toBe('secret-key');
});

it('refunds an available credit balance and refuses to exceed it (BR-FIN-05-015)', function (): void {
    $f = fin05Fixture();
    fin05Invoice($f, netMinor: 30000);
    $intent = fin05Intent($f, amountMinor: 50000);
    $intent->update(['metadata' => ['simulated_status' => 'succeeded']]);

    $body = json_encode(['gateway_reference' => $intent->fresh()->gateway_reference, 'status' => 'succeeded', 'amount_minor' => 50000, 'currency' => 'USD', 'signature' => 'valid']);
    app(IngestGatewayWebhookAction::class)->execute(new IngestGatewayWebhookData(
        driver: 'fake', headers: [], body: $body, processedByUserId: $f['user']->id,
        creditBalanceAccountId: $f['creditBalanceAccount']->id,
    ));

    $bank = Account::factory()->for($f['school'])->create();

    expect(fn () => app(RefundPaymentAction::class)->execute(new RefundPaymentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        studentId: $f['student']->id, creditBalanceAccountId: $f['creditBalanceAccount']->id,
        bankAccountGlId: $bank->id, amountMinor: 30000, currency: 'USD', reason: 'Excess refund request beyond credit',
        requestedByUserId: $f['user']->id, approvedByUserId: $f['user']->id,
    )))->toThrow(InsufficientBalanceException::class);

    $journal = app(RefundPaymentAction::class)->execute(new RefundPaymentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        studentId: $f['student']->id, creditBalanceAccountId: $f['creditBalanceAccount']->id,
        bankAccountGlId: $bank->id, amountMinor: 20000, currency: 'USD', reason: 'Refund of legitimate overpayment',
        requestedByUserId: $f['user']->id, approvedByUserId: $f['user']->id,
    ));

    expect($journal->journal_type)->toBe('REFUND');
});
