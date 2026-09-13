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
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CreateManualJournalAction;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Livewire\Accounts\Editor;
use Modules\Finance\Livewire\Accounts\Ledger;
use Modules\Finance\Livewire\Accounts\Tree;
use Modules\Finance\Livewire\CostCentres\Index as CostCentresIndex;
use Modules\Finance\Livewire\Integrity\Balances;
use Modules\Finance\Livewire\Journals\Create as JournalsCreate;
use Modules\Finance\Livewire\Journals\Index as JournalsIndex;
use Modules\Finance\Livewire\Journals\Reverse as JournalsReverse;
use Modules\Finance\Livewire\Journals\Show as JournalsShow;
use Modules\Finance\Livewire\PostingRules\Index as PostingRulesIndex;
use Modules\Finance\Livewire\Reports\TrialBalance;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AccountBalance;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\PostingRule;

/**
 * Book B FIN-01 §8 admin UI. Every screen is exercised against a real
 * permission grant (not just an authenticated user) — `AuthorizesPermissions`
 * aborts 403 with no matching grant, so a test that skips granting one
 * would only prove the screen renders for an over-privileged user, not
 * that the permission check is actually wired.
 *
 * @return array{school: School, year: AcademicYear, term: Term, cash: Account, debtors: Account, income: Account, rounding: Account}
 */
function financeAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    // BelongsToSchool's global scope silently returns zero rows with no
    // ambient SchoolContext (Book A Volume 1 principle #4) — see
    // .ai/rules/tests.md.
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();

    $cash = Account::factory()->for($school)->create(['code' => '1110', 'currency' => 'USD']);
    $debtors = Account::factory()->for($school)->controlAccount('learner')->create(['code' => '1210']);
    $income = Account::factory()->for($school)->income()->create(['code' => '4110']);
    $rounding = Account::factory()->for($school)->system('rounding')->create(['code' => '5950']);

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id,
        documentType: 'journal',
        pattern: 'JNL/{SEQ:6}',
    ));

    return [
        'school' => $school,
        'year' => $year,
        'term' => $term,
        'cash' => $cash,
        'debtors' => $debtors,
        'income' => $income,
        'rounding' => $rounding,
    ];
}

function financeAdminUser(School $school, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    // UpdateUserPermissionsAction REPLACES the user's whole direct-grant
    // set for the school on every call (see its own docblock) — every
    // permission this user needs must go in a single call, not one call
    // per permission, or each call wipes out the grant(s) before it.
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

beforeEach(function (): void {
    // Livewire's implicit route-model-binding step resolves against the
    // real route table — the routes only exist once FinanceServiceProvider
    // has booted them, which happens automatically for the app under
    // test, but `wire:navigate`/route()-helper assertions in the view
    // still need the route names registered.
    if (! Route::has('finance.accounts.tree')) {
        require base_path('Modules/Finance/routes/ledger.php');
    }
});

it('shows the chart of accounts with a live balance computed from source (Book B FIN-01 §3)', function (): void {
    $f = financeAdminFixture();
    $user = financeAdminUser($f['school'], 'finance.account.view');

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        journalType: 'MANUAL',
        narration: 'Opening cash',
        lines: [
            new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
            new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
        ],
        effectiveAt: now(),
        postedByUserId: $user->id,
    ));

    Livewire::actingAs($user)
        ->test(Tree::class, ['school' => $f['school']])
        ->assertOk()
        ->assertSee('1110')
        ->assertSee('100.00');
});

it('blocks the chart of accounts from a user with no finance.account.view grant', function (): void {
    $f = financeAdminFixture();
    $user = financeAdminUser($f['school']);

    Livewire::actingAs($user)
        ->test(Tree::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('creates an account and rejects a duplicate code with a helpful error (BR-FIN-01-019)', function (): void {
    $f = financeAdminFixture();
    $user = financeAdminUser($f['school'], 'finance.account.manage');

    Livewire::actingAs($user)
        ->test(Editor::class, ['school' => $f['school']])
        ->set('accountTypeCode', 'EXPENSE')
        ->set('code', '6100')
        ->set('name', 'Stationery')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Account::withoutGlobalScopes()->where('school_id', $f['school']->id)->where('code', '6100')->exists())->toBeTrue();

    Livewire::actingAs($user)
        ->test(Editor::class, ['school' => $f['school']])
        ->set('accountTypeCode', 'ASSET')
        ->set('code', '1110')
        ->set('name', 'Duplicate cash')
        ->call('save')
        ->assertHasErrors('code');
});

it('shows an account\'s ledger with a running balance (Book B FIN-01 §8)', function (): void {
    $f = financeAdminFixture();
    $user = financeAdminUser($f['school'], 'finance.account.view');

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        journalType: 'MANUAL',
        narration: 'First deposit',
        lines: [
            new JournalLineData($f['cash']->id, 'DR', Money::of(5000, Currency::USD)),
            new JournalLineData($f['income']->id, 'CR', Money::of(5000, Currency::USD)),
        ],
        effectiveAt: now(),
        postedByUserId: $user->id,
    ));

    Livewire::actingAs($user)
        ->test(Ledger::class, ['school' => $f['school'], 'account' => $f['cash']])
        ->assertOk()
        ->assertSee('First deposit')
        ->assertSee('50.00');
});

it('creates a cost centre from the cost centres screen (Book B FIN-01 §8)', function (): void {
    $f = financeAdminFixture();
    $user = financeAdminUser($f['school'], 'finance.cost_centre.view', 'finance.cost_centre.manage');

    Livewire::actingAs($user)
        ->test(CostCentresIndex::class, ['school' => $f['school']])
        ->call('openCreateModal')
        ->set('code', 'CC1')
        ->set('name', 'Primary section')
        ->call('create')
        ->assertHasNoErrors();

    expect(CostCentre::where('school_id', $f['school']->id)->where('code', 'CC1')->exists())->toBeTrue();
});

it('lists both draft and posted journals (Book B FIN-01 §8)', function (): void {
    $f = financeAdminFixture();
    $user = financeAdminUser($f['school'], 'finance.journal.view', 'finance.journal.create_manual');

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        journalType: 'MANUAL',
        narration: 'Posted journal',
        lines: [
            new JournalLineData($f['cash']->id, 'DR', Money::of(1000, Currency::USD)),
            new JournalLineData($f['income']->id, 'CR', Money::of(1000, Currency::USD)),
        ],
        effectiveAt: now(),
        postedByUserId: $user->id,
    ));

    app(CreateManualJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        journalType: 'MANUAL',
        narration: 'Draft journal',
        lines: [
            new JournalLineData($f['cash']->id, 'DR', Money::of(2000, Currency::USD)),
            new JournalLineData($f['income']->id, 'CR', Money::of(2000, Currency::USD)),
        ],
        effectiveAt: now(),
        postedByUserId: $user->id,
    ));

    Livewire::actingAs($user)
        ->test(JournalsIndex::class, ['school' => $f['school']])
        ->assertSee('Posted journal')
        ->assertSee('Draft journal');
});

it('creates a manual journal as a draft, never posting it directly (BR-FIN-01-018)', function (): void {
    $f = financeAdminFixture();
    $user = financeAdminUser($f['school'], 'finance.journal.create_manual');

    Livewire::actingAs($user)
        ->test(JournalsCreate::class, ['school' => $f['school']])
        ->set('narration', 'Manual adjustment')
        ->set('lines.0.account_id', $f['cash']->id)
        ->set('lines.0.direction', 'DR')
        ->set('lines.0.amount', '25.00')
        ->set('lines.1.account_id', $f['income']->id)
        ->set('lines.1.direction', 'CR')
        ->set('lines.1.amount', '25.00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $journal = Journal::where('narration', 'Manual adjustment')->sole();
    expect($journal->status)->toBe('draft');
});

it('refuses to let the creator approve their own manual journal, and posts it when a different user does (BR-FIN-01-018/AC-FIN-01-010)', function (): void {
    $f = financeAdminFixture();
    $creator = financeAdminUser($f['school'], 'finance.journal.view', 'finance.journal.approve');
    $approver = financeAdminUser($f['school'], 'finance.journal.view', 'finance.journal.approve');

    $journal = app(CreateManualJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        journalType: 'MANUAL',
        narration: 'Needs approval',
        lines: [
            new JournalLineData($f['cash']->id, 'DR', Money::of(1500, Currency::USD)),
            new JournalLineData($f['income']->id, 'CR', Money::of(1500, Currency::USD)),
        ],
        effectiveAt: now(),
        postedByUserId: $creator->id,
    ));

    Livewire::actingAs($creator)
        ->test(JournalsShow::class, ['school' => $f['school'], 'journal' => $journal])
        ->call('approve')
        ->assertDispatched('toast', variant: 'danger');

    expect($journal->fresh()->status)->toBe('draft');

    Livewire::actingAs($approver)
        ->test(JournalsShow::class, ['school' => $f['school'], 'journal' => $journal])
        ->call('approve')
        ->assertDispatched('toast', variant: 'success');

    expect($journal->fresh()->status)->toBe('posted');
});

it('reverses a posted journal into a linked mirror and refuses a second reversal (BR-FIN-01-013/015)', function (): void {
    $f = financeAdminFixture();
    $user = financeAdminUser($f['school'], 'finance.journal.reverse');

    $journal = app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        journalType: 'MANUAL',
        narration: 'To be reversed',
        lines: [
            new JournalLineData($f['cash']->id, 'DR', Money::of(3000, Currency::USD)),
            new JournalLineData($f['income']->id, 'CR', Money::of(3000, Currency::USD)),
        ],
        effectiveAt: now(),
        postedByUserId: $user->id,
    ));

    Livewire::actingAs($user)
        ->test(JournalsReverse::class, ['school' => $f['school'], 'journal' => $journal])
        ->set('reason', 'Posted to the wrong account by mistake')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $journal->refresh();
    expect($journal->isReversed())->toBeTrue();

    Livewire::actingAs($user)
        ->test(JournalsReverse::class, ['school' => $f['school'], 'journal' => $journal])
        ->assertStatus(409);
});

it('renders a balanced trial balance from source (BR-FIN-01-026)', function (): void {
    $f = financeAdminFixture();
    $user = financeAdminUser($f['school'], 'finance.report.trial_balance');

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        journalType: 'MANUAL',
        narration: 'Trial balance seed',
        lines: [
            new JournalLineData($f['cash']->id, 'DR', Money::of(8000, Currency::USD)),
            new JournalLineData($f['income']->id, 'CR', Money::of(8000, Currency::USD)),
        ],
        effectiveAt: now(),
        postedByUserId: $user->id,
    ));

    Livewire::actingAs($user)
        ->test(TrialBalance::class, ['school' => $f['school']])
        ->assertOk()
        ->assertSee('Balanced')
        ->assertSee('80.00');
});

it('creates a posting rule mapping an event to debit/credit accounts (Book B FIN-01 §4/§8)', function (): void {
    $f = financeAdminFixture();
    $user = financeAdminUser($f['school'], 'finance.posting_rule.view', 'finance.posting_rule.manage');

    Livewire::actingAs($user)
        ->test(PostingRulesIndex::class, ['school' => $f['school']])
        ->call('openEditModal')
        ->set('eventKey', 'FEE_BILLING')
        ->set('debitAccountId', $f['debtors']->id)
        ->set('creditAccountId', $f['income']->id)
        ->call('save')
        ->assertHasNoErrors();

    $rule = PostingRule::where('school_id', $f['school']->id)->where('event_key', 'FEE_BILLING')->sole();
    expect($rule->debit_account_id)->toBe($f['debtors']->id)
        ->and($rule->credit_account_id)->toBe($f['income']->id);
});

it('flags a stale cached balance against source and clears it on rebuild (BR-FIN-01-024)', function (): void {
    $f = financeAdminFixture();
    $user = financeAdminUser($f['school'], 'finance.integrity.view');

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        journalType: 'MANUAL',
        narration: 'Cache staleness seed',
        lines: [
            new JournalLineData($f['cash']->id, 'DR', Money::of(4000, Currency::USD)),
            new JournalLineData($f['income']->id, 'CR', Money::of(4000, Currency::USD)),
        ],
        effectiveAt: now(),
        postedByUserId: $user->id,
    ));

    // A deliberately wrong cache row for cash — source says 4000, cache
    // claims 0. Income's own cache row is seeded correctly so it's the
    // ONLY mismatch — otherwise income's own absent cache row (also
    // legitimately a mismatch: no cache is as wrong as a wrong one)
    // would count too, making the assertion below ambiguous.
    AccountBalance::create([
        'school_id' => $f['school']->id,
        'account_id' => $f['cash']->id,
        'term_id' => $f['term']->id,
        'currency' => 'USD',
        'opening_minor' => 0,
        'debit_minor' => 0,
        'credit_minor' => 0,
        'closing_minor' => 0,
        'line_count' => 0,
        'last_line_id' => null,
        'rebuilt_at' => now(),
    ]);

    AccountBalance::create([
        'school_id' => $f['school']->id,
        'account_id' => $f['income']->id,
        'term_id' => $f['term']->id,
        'currency' => 'USD',
        'opening_minor' => 0,
        'debit_minor' => 0,
        'credit_minor' => 4000,
        'closing_minor' => 4000,
        'line_count' => 1,
        'last_line_id' => null,
        'rebuilt_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(Balances::class, ['school' => $f['school']])
        ->assertOk()
        ->assertSee('1 mismatch')
        ->call('rebuild')
        ->assertDispatched('toast')
        ->assertSee('Cache matches source');

    expect(AccountBalance::where('school_id', $f['school']->id)->where('account_id', $f['cash']->id)->sole()->closing_minor)->toBe(4000);
});

/**
 * A real routed GET request, not `Livewire::test()` — the latter
 * instantiates the component class directly and never exercises
 * Livewire's implicit-binding-substitution step, which is exactly what
 * silently 500s on a class literally named `Index` with no registered
 * `Livewire::addLocation()` for its namespace (Book A CORE-06's own
 * "Unable to find component" incident, found only by a real browser
 * hitting a real route). `CostCentres\Index`, `Journals\Index`, and
 * `PostingRules\Index` are exactly that class-name pattern, which is why
 * `FinanceServiceProvider::registerLivewireRoutes()` now calls
 * `Livewire::addLocation()` too — this proves that registration actually
 * works end to end, not just that the component class renders in
 * isolation.
 */
it('serves every Index-named finance screen through a real routed request (Livewire implicit-binding gotcha)', function (): void {
    $f = financeAdminFixture();
    $user = financeAdminUser($f['school'], 'finance.cost_centre.view', 'finance.journal.view', 'finance.posting_rule.view');

    $this->actingAs($user)->get(route('finance.cost-centres.index', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('finance.journals.index', $f['school']))->assertOk();
    $this->actingAs($user)->get(route('finance.posting-rules.index', $f['school']))->assertOk();
});
