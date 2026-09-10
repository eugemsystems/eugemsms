<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\DuplicateRecordException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\AssertLedgerBalancedAction;
use Modules\Finance\Domain\Actions\CalculateSubledgerBalanceAction;
use Modules\Finance\Domain\Actions\CreateAccountAction;
use Modules\Finance\Domain\Actions\CreateCostCentreAction;
use Modules\Finance\Domain\Actions\DeactivateAccountAction;
use Modules\Finance\Domain\Actions\GenerateTrialBalanceAction;
use Modules\Finance\Domain\Actions\ImportOpeningBalancesAction;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\Actions\RebuildAccountBalancesAction;
use Modules\Finance\Domain\Actions\UpdateAccountAction;
use Modules\Finance\Domain\DataObjects\AssertLedgerBalancedData;
use Modules\Finance\Domain\DataObjects\CalculateSubledgerBalanceData;
use Modules\Finance\Domain\DataObjects\CreateAccountData;
use Modules\Finance\Domain\DataObjects\CreateCostCentreData;
use Modules\Finance\Domain\DataObjects\DeactivateAccountData;
use Modules\Finance\Domain\DataObjects\GenerateTrialBalanceData;
use Modules\Finance\Domain\DataObjects\ImportOpeningBalancesData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\OpeningBalanceLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\DataObjects\RebuildAccountBalancesData;
use Modules\Finance\Domain\DataObjects\UpdateAccountData;
use Modules\Finance\Domain\Exceptions\AccountHasBalanceException;
use Modules\Finance\Domain\Exceptions\UnbalancedJournalException;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AccountBalance;
use Modules\Finance\Models\Journal;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User}
 */
function balanceFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['financial_state' => 'open']);

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id,
        documentType: 'journal',
        pattern: 'JNL/{SEQ:6}',
    ));

    return ['school' => $school, 'year' => $year, 'term' => $term, 'user' => User::factory()->create()];
}

it('creates an account under a resolved account type (Book B FIN-01 §5)', function (): void {
    $f = balanceFixture();

    $account = app(CreateAccountAction::class)->execute(new CreateAccountData(
        schoolId: $f['school']->id,
        accountTypeCode: 'ASSET',
        code: '1110',
        name: 'Cash on Hand',
        createdByUserId: $f['user']->id,
    ));

    expect($account->code)->toBe('1110')
        ->and($account->is_active)->toBeTrue()
        ->and($account->accountType->code)->toBe('ASSET');
});

it('refuses a duplicate account code within the same school', function (): void {
    $f = balanceFixture();

    app(CreateAccountAction::class)->execute(new CreateAccountData(
        schoolId: $f['school']->id, accountTypeCode: 'ASSET', code: '1110', name: 'Cash', createdByUserId: $f['user']->id,
    ));

    app(CreateAccountAction::class)->execute(new CreateAccountData(
        schoolId: $f['school']->id, accountTypeCode: 'ASSET', code: '1110', name: 'Cash Again', createdByUserId: $f['user']->id,
    ));
})->throws(DuplicateRecordException::class);

it('updates an account\'s editable fields only', function (): void {
    $f = balanceFixture();
    $account = Account::factory()->for($f['school'])->create(['code' => '1110', 'name' => 'Old Name']);

    $updated = app(UpdateAccountAction::class)->execute(new UpdateAccountData(
        accountId: $account->id,
        updatedByUserId: $f['user']->id,
        name: 'New Name',
    ));

    expect($updated->name)->toBe('New Name')
        ->and($updated->code)->toBe('1110');
});

it('deactivates an account with a zero balance', function (): void {
    $f = balanceFixture();
    $account = Account::factory()->for($f['school'])->create(['code' => '1110']);

    $deactivated = app(DeactivateAccountAction::class)->execute(new DeactivateAccountData(
        accountId: $account->id,
        deactivatedByUserId: $f['user']->id,
    ));

    expect($deactivated->is_active)->toBeFalse()
        ->and($deactivated->closed_on)->not->toBeNull();
});

it('refuses to deactivate an account with a non-zero balance (BR-FIN-01-020)', function (): void {
    $f = balanceFixture();
    $cash = Account::factory()->for($f['school'])->create(['code' => '1110', 'currency' => 'USD']);
    $income = Account::factory()->for($f['school'])->income()->create(['code' => '4110']);

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        journalType: 'MANUAL',
        narration: 'Test',
        lines: [
            new JournalLineData($cash->id, 'DR', Money::of(10000, Currency::USD)),
            new JournalLineData($income->id, 'CR', Money::of(10000, Currency::USD)),
        ],
        effectiveAt: now(),
        postedByUserId: $f['user']->id,
    ));

    app(DeactivateAccountAction::class)->execute(new DeactivateAccountData(
        accountId: $cash->id,
        deactivatedByUserId: $f['user']->id,
    ));
})->throws(AccountHasBalanceException::class);

it('creates a cost centre and refuses a duplicate code', function (): void {
    $f = balanceFixture();

    app(CreateCostCentreAction::class)->execute(new CreateCostCentreData(schoolId: $f['school']->id, code: 'BRD', name: 'Boarding'));

    app(CreateCostCentreAction::class)->execute(new CreateCostCentreData(schoolId: $f['school']->id, code: 'BRD', name: 'Boarding Again'));
})->throws(DuplicateRecordException::class);

it('calculates an account balance from source lines matching the cache once rebuilt (AC-FIN-01-006/007)', function (): void {
    $f = balanceFixture();
    $cash = Account::factory()->for($f['school'])->create(['code' => '1110', 'currency' => 'USD']);
    $debtors = Account::factory()->for($f['school'])->controlAccount('learner')->create(['code' => '1210']);

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'MANUAL', narration: 'Charge',
        lines: [
            new JournalLineData($debtors->id, 'DR', Money::of(107500, Currency::USD), subledgerType: 'learner', subledgerId: 7),
            new JournalLineData($cash->id, 'CR', Money::of(107500, Currency::USD)),
        ],
        effectiveAt: now(), postedByUserId: $f['user']->id,
    ));

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'MANUAL', narration: 'Payment',
        lines: [
            new JournalLineData($cash->id, 'DR', Money::of(40000, Currency::USD)),
            new JournalLineData($debtors->id, 'CR', Money::of(40000, Currency::USD), subledgerType: 'learner', subledgerId: 7),
        ],
        effectiveAt: now(), postedByUserId: $f['user']->id,
    ));

    $subledgerBalance = app(CalculateSubledgerBalanceAction::class)->execute(new CalculateSubledgerBalanceData(
        schoolId: $f['school']->id, subledgerType: 'learner', subledgerId: 7, currency: 'USD', asAt: now(), accountId: $debtors->id,
    ));

    expect($subledgerBalance->minor)->toBe(67500)
        ->and($subledgerBalance->toDecimal())->toBe('675.00');

    app(RebuildAccountBalancesAction::class)->execute(new RebuildAccountBalancesData(schoolId: $f['school']->id));

    $cached = AccountBalance::where('account_id', $debtors->id)->where('currency', 'USD')->sole();
    expect($cached->closing_minor)->toBe(67500);
});

it('generates a per-currency and consolidated trial balance that balances (BR-FIN-01-026)', function (): void {
    $f = balanceFixture();
    $cash = Account::factory()->for($f['school'])->create(['code' => '1110', 'currency' => 'USD']);
    $income = Account::factory()->for($f['school'])->income()->create(['code' => '4110']);

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'MANUAL', narration: 'Sale',
        lines: [
            new JournalLineData($cash->id, 'DR', Money::of(10000, Currency::USD)),
            new JournalLineData($income->id, 'CR', Money::of(10000, Currency::USD)),
        ],
        effectiveAt: now(), postedByUserId: $f['user']->id,
    ));

    $trialBalance = app(GenerateTrialBalanceAction::class)->execute(new GenerateTrialBalanceData(schoolId: $f['school']->id, asAt: now()));

    expect($trialBalance->isBalanced())->toBeTrue()
        ->and($trialBalance->totalsByCurrency['USD']['debit_minor'])->toBe(10000)
        ->and($trialBalance->consolidatedBase['currency'])->toBe('USD')
        ->and($trialBalance->consolidatedBase['debit_minor'])->toBe(10000);
});

it('asserts the ledger is balanced for a school with no imbalance possible by construction (invariant I-1)', function (): void {
    $f = balanceFixture();
    $cash = Account::factory()->for($f['school'])->create(['code' => '1110', 'currency' => 'USD']);
    $income = Account::factory()->for($f['school'])->income()->create(['code' => '4110']);

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'MANUAL', narration: 'Sale',
        lines: [
            new JournalLineData($cash->id, 'DR', Money::of(10000, Currency::USD)),
            new JournalLineData($income->id, 'CR', Money::of(10000, Currency::USD)),
        ],
        effectiveAt: now(), postedByUserId: $f['user']->id,
    ));

    $assertion = app(AssertLedgerBalancedAction::class)->execute(new AssertLedgerBalancedData(schoolId: $f['school']->id));

    expect($assertion->balanced)->toBeTrue();
});

it('imports opening balances as one atomic OPENING_BALANCE journal (BR-FIN-01-025/AC-FIN-01-009-positive)', function (): void {
    $f = balanceFixture();
    $debtors = Account::factory()->for($f['school'])->controlAccount('learner')->create(['code' => '1210']);
    $openingEquity = Account::factory()->for($f['school'])->liability()->system('opening_balance_control')->create(['code' => '3300']);

    $journal = app(ImportOpeningBalancesAction::class)->execute(new ImportOpeningBalancesData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        lines: [
            new OpeningBalanceLineData($debtors->id, 'DR', Money::of(50000, Currency::USD), subledgerType: 'learner', subledgerId: 3),
            new OpeningBalanceLineData($openingEquity->id, 'CR', Money::of(50000, Currency::USD)),
        ],
        effectiveAt: now(),
        importedByUserId: $f['user']->id,
    ));

    expect($journal->journal_type)->toBe('OPENING_BALANCE')
        ->and($journal->status)->toBe('posted');
});

it('rejects an unbalanced opening balance import, posting nothing (AC-FIN-01-009)', function (): void {
    $f = balanceFixture();
    $debtors = Account::factory()->for($f['school'])->controlAccount('learner')->create(['code' => '1210']);
    $openingEquity = Account::factory()->for($f['school'])->liability()->system('opening_balance_control')->create(['code' => '3300']);

    app(ImportOpeningBalancesAction::class)->execute(new ImportOpeningBalancesData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        lines: [
            new OpeningBalanceLineData($debtors->id, 'DR', Money::of(50000, Currency::USD), subledgerType: 'learner', subledgerId: 3),
            new OpeningBalanceLineData($openingEquity->id, 'CR', Money::of(40000, Currency::USD)),
        ],
        effectiveAt: now(),
        importedByUserId: $f['user']->id,
    ));

    expect(Journal::count())->toBe(0);
})->throws(UnbalancedJournalException::class);
