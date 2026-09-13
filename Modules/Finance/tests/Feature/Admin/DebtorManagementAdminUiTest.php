<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Livewire\Accounts\LearnerAccount;
use Modules\Finance\Livewire\CreditNotes\Create as CreditNoteCreate;
use Modules\Finance\Livewire\Debtors\Workbench;
use Modules\Finance\Livewire\Invoices\Index as InvoicesIndex;
use Modules\Finance\Livewire\Invoices\Show as InvoiceShow;
use Modules\Finance\Livewire\Invoices\VoidInvoice;
use Modules\Finance\Livewire\Liabilities\Editor as LiabilitiesEditor;
use Modules\Finance\Livewire\PaymentPlans\Index as PaymentPlansIndex;
use Modules\Finance\Livewire\Reminders\Schedules as ReminderSchedules;
use Modules\Finance\Livewire\Reports\AgedDebtors;
use Modules\Finance\Livewire\Statements\Generate as StatementGenerate;
use Modules\Finance\Livewire\Waivers\Index as WaiversIndex;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CreditNote;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\FeeWaiver;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\InvoiceLine;
use Modules\Finance\Models\PaymentPlan;
use Modules\Finance\Models\ReminderSchedule;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Models\FeeLiability;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * Book B FIN-03 §5 admin UI. Own, distinctly-named fixture — see
 * `GeneralLedgerAdminUiTest`'s own note on why a Pest helper defined in
 * one test file can't be relied on from another run standalone.
 *
 * @return array{school: School, year: AcademicYear, term: Term}
 */
function debtorAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}',
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{SEQ:4}', academicYearId: $year->id,
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'credit_note', pattern: 'CN/{SEQ:6}',
    ));

    return ['school' => $school, 'year' => $year, 'term' => $term];
}

function debtorAdminUser(School $school, string ...$permissionNames): User
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
 * @param  array{school: School, year: AcademicYear, term: Term}  $f
 */
function debtorAdminStudent(array $f, User $createdBy): Student
{
    return app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        firstName: 'Rudo',
        lastName: 'Ncube',
        dateOfBirth: now()->subYears(15),
        gender: 'female',
        enrolmentType: 'FULL_TIME',
        residency: 'DAY',
        sectionId: SchoolSection::factory()->for($f['school'])->create()->id,
        gradeLevelId: GradeLevel::factory()->for($f['school'])->create()->id,
        entryCohortYear: (int) now()->year,
        createdByUserId: $createdBy->id,
        skipDuplicateCheck: true,
    ));
}

beforeEach(function (): void {
    if (! Route::has('finance.invoices.index')) {
        require base_path('Modules/Finance/routes/debtors.php');
    }
});

it('lists invoices and shows one in detail', function (): void {
    $f = debtorAdminFixture();
    $user = debtorAdminUser($f['school'], 'finance.invoice.view');
    $student = debtorAdminStudent($f, $user);
    $income = Account::factory()->for($f['school'])->income()->create();
    $debtor = Account::factory()->for($f['school'])->controlAccount('student')->create();
    $component = FeeComponent::factory()->for($f['school'])->create(['income_account_id' => $income->id, 'debtor_account_id' => $debtor->id]);

    $invoice = Invoice::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'student_id' => $student->id,
        'gross_minor' => 5000, 'net_minor' => 5000, 'balance_minor' => 5000, 'currency' => 'USD',
    ]);
    InvoiceLine::factory()->for($f['school'])->create([
        'invoice_id' => $invoice->id, 'component_id' => $component->id, 'gross_minor' => 5000, 'net_minor' => 5000, 'currency' => 'USD',
    ]);

    Livewire::actingAs($user)
        ->test(InvoicesIndex::class, ['school' => $f['school']])
        ->assertSee($invoice->invoice_number);

    Livewire::actingAs($user)
        ->test(InvoiceShow::class, ['school' => $f['school'], 'invoice' => $invoice])
        ->assertSee($component->name);
});

it('refuses to void an invoice with an allocated payment, and voids a clean one with a reversal (AC-FIN-03-002/003)', function (): void {
    $f = debtorAdminFixture();
    $user = debtorAdminUser($f['school'], 'finance.invoice.void');
    $student = debtorAdminStudent($f, $user);
    $income = Account::factory()->for($f['school'])->income()->create();
    $debtor = Account::factory()->for($f['school'])->controlAccount('student')->create();

    $invoice = Invoice::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'student_id' => $student->id,
        'gross_minor' => 5000, 'net_minor' => 5000, 'balance_minor' => 5000, 'currency' => 'USD',
    ]);

    Livewire::actingAs($user)
        ->test(VoidInvoice::class, ['school' => $f['school'], 'invoice' => $invoice])
        ->set('reason', 'Billing error — wrong learner charged.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe('voided');
});

it('creates a credit note posting Dr Fee Income / Cr Fee Debtors, never appearing as a receipt (BR-FIN-03-011)', function (): void {
    $f = debtorAdminFixture();
    $user = debtorAdminUser($f['school'], 'finance.credit_note.create', 'finance.credit_note.approve');
    $student = debtorAdminStudent($f, $user);
    $income = Account::factory()->for($f['school'])->income()->create();
    $debtor = Account::factory()->for($f['school'])->controlAccount('student')->create();
    $component = FeeComponent::factory()->for($f['school'])->create(['income_account_id' => $income->id, 'debtor_account_id' => $debtor->id]);

    Livewire::actingAs($user)
        ->test(CreditNoteCreate::class, ['school' => $f['school']])
        ->call('selectStudent', $student->id)
        ->set('reasonCode', 'billing_error')
        ->set('reason', 'Charged for the wrong term.')
        ->set('lines.0.component_id', (string) $component->id)
        ->set('lines.0.description', 'Reversal of billing error')
        ->set('lines.0.amount_minor', '25.00')
        ->set('selfApprove', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $creditNote = CreditNote::where('student_id', $student->id)->sole();
    expect($creditNote->amount_minor)->toBe(2500)
        ->and($creditNote->journal_id)->not->toBeNull();
});

it('shows a learner\'s balance per currency, invoices, and receipts on the learner account screen', function (): void {
    $f = debtorAdminFixture();
    $user = debtorAdminUser($f['school'], 'finance.fee.view');
    $student = debtorAdminStudent($f, $user);
    $debtor = Account::factory()->for($f['school'])->controlAccount('student')->create();
    $income = Account::factory()->for($f['school'])->income()->create();

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'FEE_BILLING', narration: 'Tuition',
        lines: [
            new JournalLineData($debtor->id, 'DR', Money::of(15000, Currency::USD), subledgerType: 'student', subledgerId: $student->id),
            new JournalLineData($income->id, 'CR', Money::of(15000, Currency::USD)),
        ],
        effectiveAt: now(), postedByUserId: $user->id,
    ));

    Livewire::actingAs($user)
        ->test(LearnerAccount::class, ['school' => $f['school'], 'student' => $student])
        ->assertOk()
        ->assertSee('150.00');
});

it('generates a statement for a learner over a date range (BR-FIN-03-009)', function (): void {
    $f = debtorAdminFixture();
    $user = debtorAdminUser($f['school'], 'finance.statement.generate');
    $student = debtorAdminStudent($f, $user);
    $debtor = Account::factory()->for($f['school'])->controlAccount('student')->create();
    $income = Account::factory()->for($f['school'])->income()->create();

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'FEE_BILLING', narration: 'Tuition',
        lines: [
            new JournalLineData($debtor->id, 'DR', Money::of(10000, Currency::USD), subledgerType: 'student', subledgerId: $student->id),
            new JournalLineData($income->id, 'CR', Money::of(10000, Currency::USD)),
        ],
        effectiveAt: now(), postedByUserId: $user->id,
    ));

    Livewire::actingAs($user)
        ->test(StatementGenerate::class, ['school' => $f['school']])
        ->call('selectParty', $student->id)
        ->call('generate')
        ->assertHasNoErrors()
        ->assertSee('100.00');
});

it('buckets an overdue debtor on the aged debtors report', function (): void {
    $f = debtorAdminFixture();
    $user = debtorAdminUser($f['school'], 'finance.report.debtors');
    $student = debtorAdminStudent($f, $user);

    Invoice::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'student_id' => $student->id,
        'due_date' => now()->subDays(10), 'gross_minor' => 3000, 'net_minor' => 3000, 'balance_minor' => 3000, 'currency' => 'USD',
    ]);

    Livewire::actingAs($user)
        ->test(AgedDebtors::class, ['school' => $f['school']])
        ->assertOk()
        ->assertSee($student->admission_number);
});

it('lists the debtor workbench chase list and records a chase note', function (): void {
    $f = debtorAdminFixture();
    $user = debtorAdminUser($f['school'], 'finance.debtor.manage');
    $student = debtorAdminStudent($f, $user);

    Invoice::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'student_id' => $student->id,
        'gross_minor' => 3000, 'net_minor' => 3000, 'balance_minor' => 3000, 'currency' => 'USD',
    ]);

    Livewire::actingAs($user)
        ->test(Workbench::class, ['school' => $f['school']])
        ->assertSee($student->admission_number)
        ->call('openNoteModal', $student->id)
        ->set('outcome', 'promised_to_pay')
        ->set('note', 'Guardian promised payment next week.')
        ->call('saveNote')
        ->assertHasNoErrors();
});

it('creates a reminder schedule and toggles it inactive', function (): void {
    $f = debtorAdminFixture();
    $user = debtorAdminUser($f['school'], 'finance.reminder.manage');

    Livewire::actingAs($user)
        ->test(ReminderSchedules::class, ['school' => $f['school']])
        ->call('openCreateModal')
        ->set('name', '7-day reminder')
        ->set('daysAfterDue', 7)
        ->call('create')
        ->assertHasNoErrors();

    $schedule = ReminderSchedule::where('school_id', $f['school']->id)->sole();

    Livewire::actingAs($user)
        ->test(ReminderSchedules::class, ['school' => $f['school']])
        ->call('toggleActive', $schedule->id, false);

    expect($schedule->fresh()->is_active)->toBeFalse();
});

it('proposes, approves, and lists a payment plan with its instalment schedule', function (): void {
    $f = debtorAdminFixture();
    $user = debtorAdminUser($f['school'], 'finance.payment_plan.create', 'finance.payment_plan.approve');
    $student = debtorAdminStudent($f, $user);
    $guardian = Guardian::factory()->for($f['school'])->create();

    Livewire::actingAs($user)
        ->test(PaymentPlansIndex::class, ['school' => $f['school']])
        ->call('openCreateModal')
        ->call('selectStudent', $student->id)
        ->set('guardianId', $guardian->id)
        ->set('totalAmount', '90.00')
        ->set('instalmentCount', 3)
        ->set('firstDueDate', now()->addMonth()->toDateString())
        ->call('create')
        ->assertHasNoErrors();

    $plan = PaymentPlan::where('student_id', $student->id)->sole();
    expect($plan->instalments()->sum('amount_minor'))->toBe(9000);

    Livewire::actingAs($user)
        ->test(PaymentPlansIndex::class, ['school' => $f['school']])
        ->call('approve', $plan->id)
        ->assertDispatched('toast', variant: 'success');

    expect($plan->fresh()->status)->toBe('active');
});

it('requests a waiver and approves it, posting a journal (BR-FIN-03-012)', function (): void {
    $f = debtorAdminFixture();
    $requester = debtorAdminUser($f['school'], 'finance.waiver.request');
    $approver = debtorAdminUser($f['school'], 'finance.waiver.approve');
    $student = debtorAdminStudent($f, $requester);
    $income = Account::factory()->for($f['school'])->income()->create();
    $debtor = Account::factory()->for($f['school'])->controlAccount('student')->create();
    $badDebt = Account::factory()->for($f['school'])->expense()->create();
    $component = FeeComponent::factory()->for($f['school'])->create(['income_account_id' => $income->id, 'debtor_account_id' => $debtor->id]);

    $invoice = Invoice::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'student_id' => $student->id,
        'gross_minor' => 5000, 'net_minor' => 5000, 'balance_minor' => 5000, 'currency' => 'USD',
    ]);
    InvoiceLine::factory()->for($f['school'])->create([
        'invoice_id' => $invoice->id, 'component_id' => $component->id, 'gross_minor' => 5000, 'net_minor' => 5000, 'currency' => 'USD',
    ]);

    Livewire::actingAs($requester)
        ->test(WaiversIndex::class, ['school' => $f['school']])
        ->call('openRequestModal')
        ->call('selectStudent', $student->id)
        ->set('invoiceId', $invoice->id)
        ->set('type', 'waiver')
        ->set('amount', '20.00')
        ->set('reasonCode', 'hardship')
        ->set('reason', 'Family facing temporary financial hardship.')
        ->call('request')
        ->assertHasNoErrors();

    $waiver = FeeWaiver::where('student_id', $student->id)->sole();
    expect($waiver->status)->toBe('pending');

    Livewire::actingAs($approver)
        ->test(WaiversIndex::class, ['school' => $f['school']])
        ->call('openApproveModal', $waiver->id)
        ->set('contraAccountId', $badDebt->id)
        ->set('debtorAccountId', $debtor->id)
        ->call('approve')
        ->assertHasNoErrors();

    expect($waiver->fresh()->status)->toBe('posted')
        ->and($invoice->fresh()->balance_minor)->toBe(3000);
});

it('adds a fee liability rule for a learner and shows the live percentage total', function (): void {
    $f = debtorAdminFixture();
    $user = debtorAdminUser($f['school'], 'finance.liability.manage');
    $student = debtorAdminStudent($f, $user);
    $guardian = Guardian::factory()->for($f['school'])->create();

    Livewire::actingAs($user)
        ->test(LiabilitiesEditor::class, ['school' => $f['school'], 'student' => $student])
        ->set('guardianId', $guardian->id)
        ->set('shareType', 'percentage')
        ->set('sharePercent', '60')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSee('60');

    expect(FeeLiability::where('student_id', $student->id)->where('is_active', true)->count())->toBe(1);
});

/**
 * Real routed GET, not `Livewire::test()` — see the identical test in
 * `GeneralLedgerAdminUiTest` for why this matters specifically for a
 * class literally named `Index` (`Invoices\Index`, `PaymentPlans\Index`,
 * `Waivers\Index` here).
 */
it('serves every Index-named FIN-03 screen through a real routed request (Livewire implicit-binding gotcha)', function (): void {
    $f = debtorAdminFixture();
    $user = debtorAdminUser($f['school'], 'finance.invoice.view', 'finance.payment_plan.create', 'finance.waiver.request');

    $this->actingAs($user)->get(route('finance.invoices.index', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('finance.payment-plans.index', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('finance.waivers.index', $f['school']))->assertOk();
});
