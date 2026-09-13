<?php

use App\Models\User;
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
use Modules\Finance\Domain\Actions\DeclareTillCountAction;
use Modules\Finance\Domain\Actions\OpenTillSessionAction;
use Modules\Finance\Domain\DataObjects\CreateFeeComponentData;
use Modules\Finance\Domain\DataObjects\DeclareTillCountData;
use Modules\Finance\Domain\DataObjects\OpenTillSessionData;
use Modules\Finance\Livewire\Receipts\Capture as ReceiptsCapture;
use Modules\Finance\Livewire\Receipts\Show as ReceiptShow;
use Modules\Finance\Livewire\Receipts\VoidReceipt;
use Modules\Finance\Livewire\Reports\Collections as CollectionsReport;
use Modules\Finance\Livewire\Suspense\Workbench as SuspenseWorkbench;
use Modules\Finance\Livewire\Till\Banking;
use Modules\Finance\Livewire\Till\CashUp;
use Modules\Finance\Livewire\Till\Open as TillOpen;
use Modules\Finance\Livewire\Till\Sessions as TillSessions;
use Modules\Finance\Livewire\Till\VarianceApproval;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceLine;
use Modules\Finance\Models\Receipt;
use Modules\Finance\Models\SuspenseItem;
use Modules\Finance\Models\Till;
use Modules\Finance\Models\TillSession;
use Modules\People\Models\Student;

/**
 * Book B FIN-04 §3/§4/§5 admin UI — Receipting, Cashiering & Till
 * Control. Own, distinctly-named fixture — see `GeneralLedgerAdminUiTest`'s
 * own note on why a Pest helper defined in one test file can't be
 * relied on from another run standalone.
 *
 * @return array<string, mixed>
 */
function receiptingAdminFixture(): array
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

    $cashAccount = Account::factory()->for($school)->create();
    $bankAccount = Account::factory()->for($school)->create();
    Account::factory()->for($school)->system('cash_over_short')->create();
    Account::factory()->for($school)->system('suspense')->create();
    Account::factory()->for($school)->system('credit_balance')->create();
    Account::factory()->for($school)->system('uncleared_cheque')->create();

    $till = Till::factory()->for($school)->create([
        'cash_account_id' => $cashAccount->id,
        'bank_account_id' => $bankAccount->id,
    ]);

    $income = Account::factory()->for($school)->income()->create();
    $debtor = Account::factory()->for($school)->controlAccount('student')->create();
    $creator = User::factory()->create();
    $tuition = app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
        schoolId: $school->id, code: 'TUITION', name: 'Tuition', category: 'tuition',
        incomeAccountId: $income->id, debtorAccountId: $debtor->id, defaultCurrency: 'USD', createdByUserId: $creator->id,
    ));

    $student = Student::factory()->for($school)->create();

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'till' => $till,
        'tuition' => $tuition, 'student' => $student,
    ];
}

function receiptingAdminUser(School $school, string ...$permissionNames): User
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
            userId: $user->id,
            schoolId: $school->id,
            grants: $grants,
        ));
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $f
 */
function receiptingAdminInvoice(array $f, int $netMinor = 30000): Invoice
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

it('serves Till\\Open through a real routed request', function (): void {
    $f = receiptingAdminFixture();
    $user = receiptingAdminUser($f['school'], 'finance.till.operate');

    $this->actingAs($user)
        ->get(route('finance.till.open', $f['school']))
        ->assertOk();
});

it('refuses Till\\Open to a user without finance.till.operate', function (): void {
    $f = receiptingAdminFixture();
    $user = receiptingAdminUser($f['school']);

    Livewire::actingAs($user)->test(TillOpen::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('opens a till and redirects to the capture screen', function (): void {
    $f = receiptingAdminFixture();
    $user = receiptingAdminUser($f['school'], 'finance.till.operate');

    Livewire::actingAs($user)->test(TillOpen::class, ['school' => $f['school']])
        ->set('tillId', $f['till']->id)
        ->set('openingFloat.USD', '50.00')
        ->call('open')
        ->assertRedirect();

    expect(TillSession::where('till_id', $f['till']->id)->exists())->toBeTrue();
});

it('captures a receipt against a learner and allocates it to an open invoice', function (): void {
    $f = receiptingAdminFixture();
    $cashier = receiptingAdminUser($f['school'], 'finance.till.operate', 'finance.receipt.create', 'finance.receipt.view');
    $invoice = receiptingAdminInvoice($f);

    $session = app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillId: $f['till']->id, cashierId: $cashier->id, openingFloat: ['USD' => 5000],
    ));

    Livewire::actingAs($cashier)->test(ReceiptsCapture::class, ['school' => $f['school'], 'tillSession' => $session])
        ->set('selectedStudentId', $f['student']->id)
        ->set('payerName', 'Jane Parent')
        ->set('tenders.0.amount', '300.00')
        ->call('save')
        ->assertRedirect();

    expect($invoice->fresh()->balance_minor)->toBe(0);
});

it('shows a captured receipt with its tenders and allocations', function (): void {
    $f = receiptingAdminFixture();
    $cashier = receiptingAdminUser($f['school'], 'finance.till.operate', 'finance.receipt.create', 'finance.receipt.view');
    receiptingAdminInvoice($f);

    $session = app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillId: $f['till']->id, cashierId: $cashier->id, openingFloat: ['USD' => 5000],
    ));

    Livewire::actingAs($cashier)->test(ReceiptsCapture::class, ['school' => $f['school'], 'tillSession' => $session])
        ->set('selectedStudentId', $f['student']->id)
        ->set('payerName', 'Jane Parent')
        ->set('tenders.0.amount', '300.00')
        ->call('save');

    $receipt = Receipt::sole();

    Livewire::actingAs($cashier)->test(ReceiptShow::class, ['school' => $f['school'], 'receipt' => $receipt])
        ->assertOk()
        ->assertSee($receipt->receipt_number);
});

it('voids a receipt, reversing its allocation and restoring the invoice balance', function (): void {
    $f = receiptingAdminFixture();
    $cashier = receiptingAdminUser($f['school'], 'finance.till.operate', 'finance.receipt.create', 'finance.receipt.void');
    $invoice = receiptingAdminInvoice($f);

    $session = app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillId: $f['till']->id, cashierId: $cashier->id, openingFloat: ['USD' => 5000],
    ));

    Livewire::actingAs($cashier)->test(ReceiptsCapture::class, ['school' => $f['school'], 'tillSession' => $session])
        ->set('selectedStudentId', $f['student']->id)
        ->set('payerName', 'Jane Parent')
        ->set('tenders.0.amount', '300.00')
        ->call('save');

    $receipt = Receipt::sole();
    expect($invoice->fresh()->balance_minor)->toBe(0);

    Livewire::actingAs($cashier)->test(VoidReceipt::class, ['school' => $f['school'], 'receipt' => $receipt])
        ->set('reason', 'Bank returned the cheque unpaid')
        ->call('save')
        ->assertRedirect();

    expect($invoice->fresh()->balance_minor)->toBe(30000)
        ->and($receipt->fresh()->status)->toBe('voided');
});

it('cashes up within tolerance and closes the session immediately', function (): void {
    $f = receiptingAdminFixture();
    $cashier = receiptingAdminUser($f['school'], 'finance.till.operate');

    $session = app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillId: $f['till']->id, cashierId: $cashier->id, openingFloat: ['USD' => 5000],
    ));

    Livewire::actingAs($cashier)->test(CashUp::class, ['school' => $f['school'], 'tillSession' => $session])
        ->set('declaredClosing.USD', '50.00')
        ->call('declare');

    Livewire::actingAs($cashier)->test(CashUp::class, ['school' => $f['school'], 'tillSession' => $session->fresh()])
        ->call('reveal')
        ->assertRedirect();

    expect($session->fresh()->status)->toBe('closed');
});

it('routes a beyond-tolerance variance to Till\\VarianceApproval for a different supervisor to sign off', function (): void {
    $f = receiptingAdminFixture();
    $cashier = receiptingAdminUser($f['school'], 'finance.till.operate');
    $supervisor = receiptingAdminUser($f['school'], 'finance.till.supervise');

    $session = app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillId: $f['till']->id, cashierId: $cashier->id, openingFloat: ['USD' => 5000],
    ));

    app(DeclareTillCountAction::class)->execute(new DeclareTillCountData(
        tillSessionId: $session->id, declaredClosing: ['USD' => 100000], declaredByUserId: $cashier->id,
    ));

    Livewire::actingAs($cashier)->test(CashUp::class, ['school' => $f['school'], 'tillSession' => $session->fresh()])
        ->call('reveal');

    expect($session->fresh()->status)->toBe('declaring');

    Livewire::actingAs($supervisor)->test(VarianceApproval::class, ['school' => $f['school']])
        ->assertSee($session->session_number)
        ->call('review', $session->id)
        ->set('varianceReason', 'Confirmed with cashier — extra float was added mid-shift.')
        ->call('approve');

    expect($session->fresh()->status)->toBe('closed')
        ->and($session->fresh()->supervised_by)->toBe($supervisor->id);
});

it('lists till sessions filterable by till and cashier', function (): void {
    $f = receiptingAdminFixture();
    $cashier = receiptingAdminUser($f['school'], 'finance.till.operate', 'finance.till.view');

    app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillId: $f['till']->id, cashierId: $cashier->id, openingFloat: ['USD' => 5000],
    ));

    Livewire::actingAs($cashier)->test(TillSessions::class, ['school' => $f['school']])
        ->assertOk()
        ->set('tillId', $f['till']->id)
        ->assertOk();
});

it('shows the daily banking sheet totalled from posted receipts', function (): void {
    $f = receiptingAdminFixture();
    $cashier = receiptingAdminUser($f['school'], 'finance.till.operate', 'finance.receipt.create', 'finance.till.view');
    receiptingAdminInvoice($f);

    $session = app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillId: $f['till']->id, cashierId: $cashier->id, openingFloat: ['USD' => 5000],
    ));

    Livewire::actingAs($cashier)->test(ReceiptsCapture::class, ['school' => $f['school'], 'tillSession' => $session])
        ->set('selectedStudentId', $f['student']->id)
        ->set('payerName', 'Jane Parent')
        ->set('tenders.0.amount', '300.00')
        ->call('save');

    Livewire::actingAs($cashier)->test(Banking::class, ['school' => $f['school']])
        ->assertOk()
        ->assertSee('300.00');
});

it('resolves a suspense item to a learner, allocating it against an open invoice', function (): void {
    $f = receiptingAdminFixture();
    $cashier = receiptingAdminUser($f['school'], 'finance.till.operate', 'finance.receipt.create');
    $manager = receiptingAdminUser($f['school'], 'finance.suspense.manage');
    $invoice = receiptingAdminInvoice($f);

    $session = app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillId: $f['till']->id, cashierId: $cashier->id, openingFloat: ['USD' => 5000],
    ));

    Livewire::actingAs($cashier)->test(ReceiptsCapture::class, ['school' => $f['school'], 'tillSession' => $session])
        ->set('payerName', 'Unidentified deposit')
        ->set('tenders.0.amount', '300.00')
        ->call('save');

    $item = SuspenseItem::sole();

    Livewire::actingAs($manager)->test(SuspenseWorkbench::class, ['school' => $f['school']])
        ->call('startResolving', $item->id)
        ->set('selectedStudentId', $f['student']->id)
        ->call('resolve');

    expect($invoice->fresh()->balance_minor)->toBe(0)
        ->and($item->fresh()->status)->toBe('resolved');
});

it('shows the collections report broken down by day, tender, and cashier', function (): void {
    $f = receiptingAdminFixture();
    $cashier = receiptingAdminUser($f['school'], 'finance.till.operate', 'finance.receipt.create', 'finance.report.collections');
    receiptingAdminInvoice($f);

    $session = app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        tillId: $f['till']->id, cashierId: $cashier->id, openingFloat: ['USD' => 5000],
    ));

    Livewire::actingAs($cashier)->test(ReceiptsCapture::class, ['school' => $f['school'], 'tillSession' => $session])
        ->set('selectedStudentId', $f['student']->id)
        ->set('payerName', 'Jane Parent')
        ->set('tenders.0.amount', '300.00')
        ->call('save');

    Livewire::actingAs($cashier)->test(CollectionsReport::class, ['school' => $f['school']])
        ->assertOk()
        ->assertSee('300.00');
});
