<?php

use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CreateFeeComponentAction;
use Modules\Finance\Domain\Actions\IngestGatewayWebhookAction;
use Modules\Finance\Domain\Actions\InitiatePaymentAction;
use Modules\Finance\Domain\DataObjects\CreateFeeComponentData;
use Modules\Finance\Domain\DataObjects\IngestGatewayWebhookData;
use Modules\Finance\Domain\DataObjects\InitiatePaymentData;
use Modules\Finance\Livewire\Bank\Accounts as BankAccountsScreen;
use Modules\Finance\Livewire\Bank\Import as BankImport;
use Modules\Finance\Livewire\Bank\Matching as BankMatching;
use Modules\Finance\Livewire\Gateways\Index as GatewaysIndex;
use Modules\Finance\Livewire\Gateways\Intents as GatewayIntents;
use Modules\Finance\Livewire\Gateways\Webhooks as GatewayWebhooksScreen;
use Modules\Finance\Livewire\Reconciliation\Dashboard as ReconciliationDashboard;
use Modules\Finance\Livewire\Reconciliation\Exceptions as ReconciliationExceptions;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Models\BankStatement;
use Modules\Finance\Models\BankStatementLine;
use Modules\Finance\Models\GatewayWebhook;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceLine;
use Modules\Finance\Models\PaymentGateway;
use Modules\Finance\Models\Receipt;
use Modules\Finance\Models\ReconciliationRun;
use Modules\People\Models\Student;

/**
 * Book B FIN-05 §3/§5/§6/§7/§8 admin UI — Payment Gateways &
 * Reconciliation. Own, distinctly-named fixture — see
 * `GeneralLedgerAdminUiTest`'s own note on why a Pest helper defined
 * in one test file can't be relied on from another run standalone.
 *
 * @return array<string, mixed>
 */
function gatewaysAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}',
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'receipt', pattern: 'RCT/{SEQ:6}',
    ));

    Account::factory()->for($school)->system('cash_over_short')->create();
    Account::factory()->for($school)->system('suspense')->create();
    Account::factory()->for($school)->system('credit_balance')->create();
    Account::factory()->for($school)->system('uncleared_cheque')->create();

    $settlementAccount = Account::factory()->for($school)->create();
    $feeAccount = Account::factory()->for($school)->create();
    $glBankAccount = Account::factory()->for($school)->create();

    $gateway = PaymentGateway::factory()->create([
        'school_id' => $school->id,
        'settlement_account_id' => $settlementAccount->id,
        'fee_account_id' => $feeAccount->id,
    ]);

    $bankAccount = BankAccount::factory()->for($school)->create(['gl_account_id' => $glBankAccount->id, 'currency' => 'USD']);

    $income = Account::factory()->for($school)->income()->create();
    $debtor = Account::factory()->for($school)->controlAccount('student')->create();
    $creator = User::factory()->create();
    $tuition = app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
        schoolId: $school->id, code: 'TUITION', name: 'Tuition', category: 'tuition',
        incomeAccountId: $income->id, debtorAccountId: $debtor->id, defaultCurrency: 'USD', createdByUserId: $creator->id,
    ));

    $student = Student::factory()->for($school)->create();

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'gateway' => $gateway,
        'bankAccount' => $bankAccount, 'tuition' => $tuition, 'student' => $student,
    ];
}

function gatewaysAdminUser(School $school, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        [$moduleCode, $resource, $action] = explode('.', $permissionName);

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($moduleCode), 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    if ($grants !== []) {
        app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
            userId: $user->id, schoolId: $school->id, grants: $grants,
        ));
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $f
 */
function gatewaysAdminInvoice(array $f, int $netMinor = 10000): Invoice
{
    $invoice = Invoice::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $f['student']->id, 'billed_party_id' => 501, 'gross_minor' => $netMinor,
        'net_minor' => $netMinor, 'balance_minor' => $netMinor, 'currency' => 'USD', 'due_date' => now()->toDateString(),
    ]);

    InvoiceLine::factory()->create([
        'school_id' => $f['school']->id, 'invoice_id' => $invoice->id, 'component_id' => $f['tuition']->id,
        'gross_minor' => $netMinor, 'net_minor' => $netMinor, 'currency' => 'USD',
        'allocation_priority' => $f['tuition']->allocation_priority, 'tax_category' => $f['tuition']->tax_category,
    ]);

    return $invoice->fresh('lines');
}

it('serves Gateways\\Index through a real routed request', function (): void {
    $f = gatewaysAdminFixture();
    $user = gatewaysAdminUser($f['school'], 'finance.gateway.manage');

    $this->actingAs($user)
        ->get(route('finance.gateways.index', $f['school']))
        ->assertOk();
});

it('refuses Gateways\\Index to a user without finance.gateway.manage', function (): void {
    $f = gatewaysAdminFixture();
    $user = gatewaysAdminUser($f['school']);

    Livewire::actingAs($user)->test(GatewaysIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('registers a payment gateway with encrypted-at-rest credentials (BR-FIN-05-016)', function (): void {
    $f = gatewaysAdminFixture();
    $user = gatewaysAdminUser($f['school'], 'finance.gateway.manage');

    // `payment_gateways` has a real UNIQUE(school_id, driver) constraint,
    // and only the `fake` driver is registered in this build — a school
    // can have at most one gateway until a second real driver exists.
    // Remove the fixture's own gateway first so this school can register one.
    $f['gateway']->delete();
    $settlement = Account::factory()->for($f['school'])->create();
    $fee = Account::factory()->for($f['school'])->create();

    Livewire::actingAs($user)->test(GatewaysIndex::class, ['school' => $f['school']])
        ->set('name', 'Second Gateway')
        ->set('credentials', 'super-secret-key')
        ->set('supportedMethods', ['ecocash'])
        ->set('supportedCurrencies', ['USD'])
        ->set('settlementAccountId', $settlement->id)
        ->set('feeAccountId', $fee->id)
        ->set('isDefault', true)
        ->set('isActive', true)
        ->call('save');

    $created = PaymentGateway::where('name', 'Second Gateway')->sole();
    expect($created->is_default)->toBeTrue()
        ->and($created->credentials)->toBe('super-secret-key')
        ->and($created->getRawOriginal('credentials'))->not->toBe('super-secret-key');
});

it('clears another gateway\'s default flag when a different gateway is made the default (BR-FIN-05-018)', function (): void {
    $f = gatewaysAdminFixture();
    $user = gatewaysAdminUser($f['school'], 'finance.gateway.manage');
    expect($f['gateway']->is_default)->toBeTrue();

    $otherGateway = PaymentGateway::factory()->create([
        'school_id' => $f['school']->id, 'driver' => 'fake_two', 'is_default' => false,
    ]);

    Livewire::actingAs($user)->test(GatewaysIndex::class, ['school' => $f['school']])
        ->call('openEditModal', $otherGateway->id)
        ->set('isDefault', true)
        ->call('save');

    expect($otherGateway->fresh()->is_default)->toBeTrue()
        ->and($f['gateway']->fresh()->is_default)->toBeFalse();
});

it('checks a gateway\'s health and records the result', function (): void {
    $f = gatewaysAdminFixture();
    $user = gatewaysAdminUser($f['school'], 'finance.gateway.manage');

    Livewire::actingAs($user)->test(GatewaysIndex::class, ['school' => $f['school']])
        ->call('testConnection', $f['gateway']->id);

    expect($f['gateway']->fresh()->health_status)->toBe('up')
        ->and($f['gateway']->fresh()->last_health_check_at)->not->toBeNull();
});

it('polls a pending intent and settles it into a real receipt once the driver reports success (BR-FIN-05-007)', function (): void {
    $f = gatewaysAdminFixture();
    $cashier = gatewaysAdminUser($f['school'], 'finance.gateway.view');
    $invoice = gatewaysAdminInvoice($f);

    $intent = app(InitiatePaymentAction::class)->execute(new InitiatePaymentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        gatewayId: $f['gateway']->id, idempotencyKey: (string) Str::uuid(),
        payerName: 'Jane Parent', purpose: 'fees', amountMinor: 10000, currency: 'USD', studentId: $f['student']->id,
    ));
    $intent->update(['metadata' => ['simulated_status' => 'succeeded']]);

    Livewire::actingAs($cashier)->test(GatewayIntents::class, ['school' => $f['school']])
        ->call('poll', $intent->id);

    expect($intent->fresh()->status)->toBe('succeeded')
        ->and($invoice->fresh()->balance_minor)->toBe(0);
});

it('force-settles a payment intent without gateway confirmation (AC-FIN-05 force-settle, dangerous)', function (): void {
    $f = gatewaysAdminFixture();
    $supervisor = gatewaysAdminUser($f['school'], 'finance.gateway.view', 'finance.gateway.force_settle');

    $intent = app(InitiatePaymentAction::class)->execute(new InitiatePaymentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        gatewayId: $f['gateway']->id, idempotencyKey: (string) Str::uuid(),
        payerName: 'John Payer', purpose: 'wallet_topup', amountMinor: 5000, currency: 'USD',
    ));

    Livewire::actingAs($supervisor)->test(GatewayIntents::class, ['school' => $f['school']])
        ->call('openForceSettle', $intent->id)
        ->set('forceAmount', '50.00')
        ->call('forceSettle');

    expect($intent->fresh()->status)->toBe('succeeded');
});

it('reprocesses a failed webhook once its matching intent now exists, without a second delivery row (BR-FIN-05-004)', function (): void {
    $f = gatewaysAdminFixture();
    $reviewer = gatewaysAdminUser($f['school'], 'finance.gateway.view');

    $intent = app(InitiatePaymentAction::class)->execute(new InitiatePaymentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        gatewayId: $f['gateway']->id, idempotencyKey: (string) Str::uuid(),
        payerName: 'Late Binder', purpose: 'wallet_topup', amountMinor: 2000, currency: 'USD',
    ));

    $body = json_encode(['gateway_reference' => 'FAKE-REF-1', 'status' => 'succeeded', 'amount_minor' => 2000, 'currency' => 'USD', 'signature' => 'valid']);

    Livewire::actingAs($reviewer)->test(GatewayWebhooksScreen::class, ['school' => $f['school']]);

    $webhook = app(IngestGatewayWebhookAction::class)->execute(new IngestGatewayWebhookData(
        driver: 'fake', headers: [], body: $body, processedByUserId: $reviewer->id,
    ));
    expect($webhook->processing_status)->toBe('failed');

    $intent->update(['gateway_reference' => 'FAKE-REF-1']);

    Livewire::actingAs($reviewer)->test(GatewayWebhooksScreen::class, ['school' => $f['school']])
        ->call('reprocess', $webhook->id);

    expect($webhook->fresh()->processing_status)->toBe('processed')
        ->and($intent->fresh()->status)->toBe('succeeded')
        ->and(GatewayWebhook::where('payload_hash', $webhook->payload_hash)->count())->toBe(1);
});

it('creates a bank account', function (): void {
    $f = gatewaysAdminFixture();
    $user = gatewaysAdminUser($f['school'], 'finance.bank.manage');
    $gl = Account::factory()->for($f['school'])->create();

    Livewire::actingAs($user)->test(BankAccountsScreen::class, ['school' => $f['school']])
        ->set('glAccountId', $gl->id)
        ->set('bankName', 'CBZ Bank')
        ->set('accountName', 'School Operating Account')
        ->set('accountNumber', '1234567890')
        ->set('currency', 'USD')
        ->set('accountType', 'current')
        ->call('save');

    expect(BankAccount::where('account_number', '1234567890')->exists())->toBeTrue();
});

it('imports a CSV bank statement, mapping columns, and auto-matches an exact reference (BR-FIN-05-011)', function (): void {
    $f = gatewaysAdminFixture();
    $user = gatewaysAdminUser($f['school'], 'finance.bank.reconcile', 'finance.gateway.view');
    $invoice = gatewaysAdminInvoice($f);

    $cashier = gatewaysAdminUser($f['school'], 'finance.gateway.view', 'finance.receipt.create', 'finance.till.operate');
    $intent = app(InitiatePaymentAction::class)->execute(new InitiatePaymentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        gatewayId: $f['gateway']->id, idempotencyKey: (string) Str::uuid(),
        payerName: 'Jane Parent', purpose: 'fees', amountMinor: 10000, currency: 'USD', studentId: $f['student']->id,
    ));
    $intent->update(['metadata' => ['simulated_status' => 'succeeded']]);
    Livewire::actingAs($cashier)->test(GatewayIntents::class, ['school' => $f['school']])->call('poll', $intent->id);

    $receipt = Receipt::sole();
    $receiptNumber = $receipt->receipt_number;

    $csv = "Date,Description,Reference,Debit,Credit\n".now()->format('d/m/Y').",Gateway settlement,{$receiptNumber},,100.00";
    $file = TemporaryUploadedFile::fake()->createWithContent('statement.csv', $csv);

    $component = Livewire::actingAs($user)->test(BankImport::class, ['school' => $f['school']])
        ->set('bankAccountId', $f['bankAccount']->id)
        ->set('statementFrom', now()->subDays(7)->toDateString())
        ->set('statementTo', now()->toDateString())
        ->set('openingBalance', '0')
        ->set('closingBalance', '100.00')
        ->set('file', $file);

    $component->set('dateColumn', 'Date')
        ->set('descriptionColumn', 'Description')
        ->set('referenceColumn', 'Reference')
        ->set('creditColumn', 'Credit')
        ->call('import')
        ->assertRedirect();

    $line = BankStatementLine::sole();
    expect($line->match_status)->toBe('auto_matched')
        ->and($line->matched_id)->toBe($receipt->id)
        ->and($invoice->fresh()->balance_minor)->toBe(0);
});

it('manually matches an unmatched credit line to a candidate receipt, and converts another to suspense (BR-FIN-05-012)', function (): void {
    $f = gatewaysAdminFixture();
    $user = gatewaysAdminUser($f['school'], 'finance.bank.reconcile', 'finance.receipt.create', 'finance.till.operate', 'finance.gateway.view');

    $invoice = gatewaysAdminInvoice($f, 15000);
    $intent = app(InitiatePaymentAction::class)->execute(new InitiatePaymentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        gatewayId: $f['gateway']->id, idempotencyKey: (string) Str::uuid(),
        payerName: 'Jane Parent', purpose: 'fees', amountMinor: 15000, currency: 'USD', studentId: $f['student']->id,
    ));
    $intent->update(['metadata' => ['simulated_status' => 'succeeded']]);
    Livewire::actingAs($user)->test(GatewayIntents::class, ['school' => $f['school']])->call('poll', $intent->id);
    $receipt = Receipt::sole();

    $statement = BankStatement::factory()->for($f['school'])->create(['bank_account_id' => $f['bankAccount']->id, 'line_count' => 2]);
    $matchableLine = BankStatementLine::factory()->for($f['school'])->create([
        'statement_id' => $statement->id, 'line_number' => 1, 'credit_minor' => 15000, 'currency' => 'USD',
        'transaction_date' => $receipt->effective_date, 'match_status' => 'unmatched',
    ]);
    $suspenseLine = BankStatementLine::factory()->for($f['school'])->create([
        'statement_id' => $statement->id, 'line_number' => 2, 'credit_minor' => 7500, 'currency' => 'USD', 'match_status' => 'unmatched',
    ]);

    Livewire::actingAs($user)->test(BankMatching::class, ['school' => $f['school'], 'statement' => $statement])
        ->call('startMatching', $matchableLine->id)
        ->call('confirmMatch', $receipt->id, 95);

    expect($matchableLine->fresh()->match_status)->toBe('manually_matched')
        ->and($matchableLine->fresh()->matched_id)->toBe($receipt->id);

    Livewire::actingAs($user)->test(BankMatching::class, ['school' => $f['school'], 'statement' => $statement])
        ->call('convertToSuspense', $suspenseLine->id);

    expect($suspenseLine->fresh()->match_status)->toBe('manually_matched')
        ->and(Receipt::where('id', $suspenseLine->fresh()->matched_id)->sole()->is_suspense)->toBeTrue();
});

it('runs a bank-scope reconciliation and surfaces an unmatched credit as a BANK_NO_RECEIPT exception (AC-FIN-05-007)', function (): void {
    $f = gatewaysAdminFixture();
    $user = gatewaysAdminUser($f['school'], 'finance.reconciliation.view');

    $statement = BankStatement::factory()->for($f['school'])->create(['bank_account_id' => $f['bankAccount']->id]);
    BankStatementLine::factory()->for($f['school'])->create([
        'statement_id' => $statement->id, 'credit_minor' => 3000, 'currency' => 'USD', 'match_status' => 'unmatched', 'description' => 'Unexplained deposit',
    ]);

    Livewire::actingAs($user)->test(ReconciliationDashboard::class, ['school' => $f['school']])
        ->set('scope', 'bank')
        ->set('bankAccountId', $f['bankAccount']->id)
        ->call('run');

    $run = ReconciliationRun::sole();
    expect($run->status)->toBe('exceptions')
        ->and($run->exception_count)->toBe(1)
        ->and($run->exceptions[0]['class'])->toBe('BANK_NO_RECEIPT');
});

it('resolves a reconciliation exception with a recorded note, and marks the run reviewed once every exception is closed (BR-FIN-05-013)', function (): void {
    $f = gatewaysAdminFixture();
    $user = gatewaysAdminUser($f['school'], 'finance.reconciliation.resolve', 'finance.gateway.view');

    $run = ReconciliationRun::factory()->for($f['school'])->create([
        'exception_count' => 1,
        'exceptions' => [['class' => 'RECEIPT_NO_GATEWAY', 'gateway_reference' => 'FAKE-999', 'amount_minor' => 1000]],
        'status' => 'exceptions',
    ]);

    Livewire::actingAs($user)->test(ReconciliationExceptions::class, ['school' => $f['school']])
        ->call('startResolving', $run->id, 0)
        ->set('resolutionNote', 'Confirmed as a gateway reporting delay; no fraud found.')
        ->call('resolve');

    $fresh = $run->fresh();
    expect($fresh->exceptions[0]['resolution_note'])->toBe('Confirmed as a gateway reporting delay; no fraud found.')
        ->and($fresh->reviewed_by)->toBe($user->id)
        ->and($fresh->reviewed_at)->not->toBeNull();
});

it('converts a BANK_NO_RECEIPT exception to suspense and resolves it in one step', function (): void {
    $f = gatewaysAdminFixture();
    $user = gatewaysAdminUser($f['school'], 'finance.reconciliation.resolve', 'finance.bank.reconcile');

    $statement = BankStatement::factory()->for($f['school'])->create(['bank_account_id' => $f['bankAccount']->id]);
    $line = BankStatementLine::factory()->for($f['school'])->create([
        'statement_id' => $statement->id, 'credit_minor' => 4500, 'currency' => 'USD', 'match_status' => 'unmatched', 'description' => 'Unexplained deposit',
    ]);

    $run = ReconciliationRun::factory()->for($f['school'])->create([
        'exception_count' => 1,
        'exceptions' => [['class' => 'BANK_NO_RECEIPT', 'bank_statement_line_id' => $line->id, 'amount_minor' => 4500, 'description' => 'Unexplained deposit']],
        'status' => 'exceptions',
    ]);

    Livewire::actingAs($user)->test(ReconciliationExceptions::class, ['school' => $f['school']])
        ->call('convertToSuspense', $run->id, 0, $line->id);

    expect($line->fresh()->match_status)->toBe('manually_matched')
        ->and($run->fresh()->reviewed_at)->not->toBeNull();
});
