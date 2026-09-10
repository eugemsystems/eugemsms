<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Exceptions\PeriodLockedException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\ApproveManualJournalAction;
use Modules\Finance\Domain\Actions\CreateManualJournalAction;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\Actions\ReverseJournalAction;
use Modules\Finance\Domain\DataObjects\ApproveManualJournalData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\DataObjects\ReverseJournalData;
use Modules\Finance\Domain\Exceptions\AccountNotPostableException;
use Modules\Finance\Domain\Exceptions\CrossSchoolAccountException;
use Modules\Finance\Domain\Exceptions\CurrencyRestrictedAccountException;
use Modules\Finance\Domain\Exceptions\JournalAlreadyReversedException;
use Modules\Finance\Domain\Exceptions\MissingCostCentreException;
use Modules\Finance\Domain\Exceptions\MissingSubledgerException;
use Modules\Finance\Domain\Exceptions\UnbalancedJournalException;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Journal;

/**
 * @return array{school: School, year: AcademicYear, term: Term, cash: Account, debtors: Account, income: Account, rounding: Account, userA: User, userB: User}
 */
function ledgerFixture(string $financialState = 'open'): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    // Every read that isn't explicitly withoutGlobalScopes() goes through
    // SchoolScope, which silently returns nothing with no ambient
    // SchoolContext — real requests always have one (CORE-02's
    // tenant-resolution middleware sets it), so tests must too.
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    // `financial_state` is only ever set here, at creation — Term's
    // GuardsPeriodStateWrites trait refuses a direct ->update() on this
    // column outside ACT-TransitionPeriodState (Book A CORE-03), and
    // creating a row with an initial state isn't a transition.
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['financial_state' => $financialState]);

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
        'userA' => User::factory()->create(),
        'userB' => User::factory()->create(),
    ];
}

function postJournalData(array $f, array $lines, array $overrides = []): PostJournalData
{
    return new PostJournalData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        journalType: $overrides['journalType'] ?? 'MANUAL',
        narration: $overrides['narration'] ?? 'Test journal',
        lines: $lines,
        effectiveAt: $overrides['effectiveAt'] ?? now(),
        postedByUserId: $overrides['postedByUserId'] ?? $f['userA']->id,
        overrideSoftClose: $overrides['overrideSoftClose'] ?? false,
    );
}

it('posts a balanced journal with matching debits and credits (Book B FIN-01 §6)', function (): void {
    $f = ledgerFixture();

    $journal = app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ]));

    expect($journal->status)->toBe('posted')
        ->and($journal->journal_number)->toStartWith('JNL/')
        ->and($journal->lines)->toHaveCount(2);
});

it('rejects an unbalanced journal and writes nothing (BR-FIN-01-002/003/AC-FIN-01-001)', function (): void {
    $f = ledgerFixture();

    expect(fn () => app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(50000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(40000, Currency::USD)),
    ])))->toThrow(UnbalancedJournalException::class);

    expect(Journal::count())->toBe(0)
        ->and(DB::table('journal_lines')->count())->toBe(0);
});

it('rejects a journal that balances in one currency but not another (AC-FIN-01-002)', function (): void {
    $f = ledgerFixture();
    $zwgIncome = Account::factory()->for($f['school'])->income()->create(['code' => '4120']);
    $zwgClearing = Account::factory()->for($f['school'])->create(['code' => '1140']);

    expect(fn () => app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
        new JournalLineData($zwgClearing->id, 'DR', Money::of(500000, Currency::ZWG)),
        new JournalLineData($zwgIncome->id, 'CR', Money::of(400000, Currency::ZWG)),
    ])))->toThrow(UnbalancedJournalException::class);
});

it('requires at least two lines (BR-FIN-01-002)', function (): void {
    $f = ledgerFixture();

    app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
    ]));
})->throws(RuntimeException::class);

it('refuses to post to a non-postable header account (BR-FIN-01-006)', function (): void {
    $f = ledgerFixture();
    $header = Account::factory()->for($f['school'])->header()->create(['code' => '1000']);

    expect(fn () => app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($header->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ])))->toThrow(AccountNotPostableException::class);
});

it('refuses to post to an account belonging to a different school (BR-FIN-01-007)', function (): void {
    $f = ledgerFixture();
    $otherSchool = School::factory()->create();
    $foreignAccount = Account::factory()->for($otherSchool)->create();

    expect(fn () => app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($foreignAccount->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ])))->toThrow(CrossSchoolAccountException::class);
});

it('refuses a line missing a required cost centre (BR-FIN-01-008)', function (): void {
    $f = ledgerFixture();
    $expense = Account::factory()->for($f['school'])->expense()->create(['code' => '5110', 'requires_cost_centre' => true]);

    expect(fn () => app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($expense->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['cash']->id, 'CR', Money::of(10000, Currency::USD)),
    ])))->toThrow(MissingCostCentreException::class);
});

it('refuses a control-account line with no subledger reference (BR-FIN-01-009)', function (): void {
    $f = ledgerFixture();

    expect(fn () => app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['debtors']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ])))->toThrow(MissingSubledgerException::class);
});

it('allows a control-account line that carries its subledger reference', function (): void {
    $f = ledgerFixture();

    $journal = app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['debtors']->id, 'DR', Money::of(10000, Currency::USD), subledgerType: 'learner', subledgerId: 42),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ]));

    expect($journal->lines->firstWhere('account_id', $f['debtors']->id)->subledger_id)->toBe(42);
});

it('refuses a line whose currency conflicts with a currency-restricted account (BR-FIN-01-010)', function (): void {
    $f = ledgerFixture();

    expect(fn () => app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(500000, Currency::ZWG)),
        new JournalLineData($f['income']->id, 'CR', Money::of(500000, Currency::ZWG)),
    ])))->toThrow(CurrencyRestrictedAccountException::class);
});

it('is append-only: journal_lines refuses update and delete (BR-FIN-01-011/AC-FIN-01-003)', function (): void {
    $f = ledgerFixture();
    $journal = app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ]));

    $line = $journal->lines->first();

    expect(fn () => $line->update(['amount_minor' => 1]))->toThrow(InvalidStateTransitionException::class);
    expect(fn () => $line->delete())->toThrow(InvalidStateTransitionException::class);
});

it('permits only status, reversed_by_journal_id, and approved_by to change on journals (BR-FIN-01-012)', function (): void {
    $f = ledgerFixture();
    $journal = app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ]));

    expect(fn () => $journal->update(['narration' => 'tampered']))->toThrow(InvalidStateTransitionException::class);
});

it('reverses a posted journal, mirroring every line and linking both directions (BR-FIN-01-013/AC-FIN-01-004)', function (): void {
    $f = ledgerFixture();
    $original = app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ]));
    $originalLinesBefore = $original->lines->toArray();

    $reversal = app(ReverseJournalAction::class)->execute(new ReverseJournalData(
        journalId: $original->id,
        reason: 'Posted to the wrong income account by mistake.',
        reversedByUserId: $f['userA']->id,
    ));

    expect($reversal->is_reversal)->toBeTrue()
        ->and($reversal->reverses_journal_id)->toBe($original->id)
        ->and($original->fresh()->reversed_by_journal_id)->toBe($reversal->id)
        ->and($reversal->lines->firstWhere('account_id', $f['cash']->id)->direction)->toBe('CR')
        ->and($reversal->lines->firstWhere('account_id', $f['income']->id)->direction)->toBe('DR');

    // The original journal's own lines are byte-identical to before —
    // append-only means a reversal can never alter them.
    foreach ($original->fresh()->lines as $i => $line) {
        expect($line->amount_minor)->toBe($originalLinesBefore[$i]['amount_minor'])
            ->and($line->direction)->toBe($originalLinesBefore[$i]['direction']);
    }
});

it('requires a reversal reason of at least 15 characters (BR-FIN-01-013)', function (): void {
    $f = ledgerFixture();
    $original = app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ]));

    app(ReverseJournalAction::class)->execute(new ReverseJournalData(
        journalId: $original->id,
        reason: 'too short',
        reversedByUserId: $f['userA']->id,
    ));
})->throws(ValidationException::class);

it('refuses to reverse an already-reversed journal (BR-FIN-01-015)', function (): void {
    $f = ledgerFixture();
    $original = app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ]));

    app(ReverseJournalAction::class)->execute(new ReverseJournalData(
        journalId: $original->id,
        reason: 'First reversal, entirely legitimate reason.',
        reversedByUserId: $f['userA']->id,
    ));

    app(ReverseJournalAction::class)->execute(new ReverseJournalData(
        journalId: $original->id,
        reason: 'Second attempt at reversing the same journal.',
        reversedByUserId: $f['userA']->id,
    ));
})->throws(JournalAlreadyReversedException::class);

it('refuses to post into a locked period (BR-FIN-01-016/AC-FIN-01-005)', function (): void {
    $f = ledgerFixture(financialState: 'locked');

    app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ]));
})->throws(PeriodLockedException::class);

it('permits posting into a soft-closed period only with an approved override (BR-FIN-01-016)', function (): void {
    $f = ledgerFixture(financialState: 'soft_closed');

    expect(fn () => app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ])))->toThrow(PeriodLockedException::class);

    $journal = app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ], ['overrideSoftClose' => true]));

    expect($journal->status)->toBe('posted')
        ->and($journal->is_prior_period_adjustment)->toBeTrue();
});

it('creates a manual journal as draft and does not post it without approval (BR-FIN-01-018/AC-FIN-01-010)', function (): void {
    $f = ledgerFixture();

    $draft = app(CreateManualJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ]));

    expect($draft->status)->toBe('draft')
        ->and($draft->approved_by)->toBeNull();
});

it('refuses to let the creator approve their own manual journal (BR-FIN-01-018)', function (): void {
    $f = ledgerFixture();

    $draft = app(CreateManualJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ], ['postedByUserId' => $f['userA']->id]));

    app(ApproveManualJournalAction::class)->execute(new ApproveManualJournalData(
        journalId: $draft->id,
        approvedByUserId: $f['userA']->id,
    ));
})->throws(InvalidStateTransitionException::class);

it('posts a manual journal once a different user approves it', function (): void {
    $f = ledgerFixture();

    $draft = app(CreateManualJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ], ['postedByUserId' => $f['userA']->id]));

    $posted = app(ApproveManualJournalAction::class)->execute(new ApproveManualJournalData(
        journalId: $draft->id,
        approvedByUserId: $f['userB']->id,
    ));

    expect($posted->status)->toBe('posted')
        ->and($posted->approved_by)->toBe($f['userB']->id);

    $this->assertDatabaseHas('financial_audit_log', ['event_type' => 'manual_journal_approved']);
});

it('records a synchronous financial audit entry for every posted journal', function (): void {
    $f = ledgerFixture();
    $journal = app(PostJournalAction::class)->execute(postJournalData($f, [
        new JournalLineData($f['cash']->id, 'DR', Money::of(10000, Currency::USD)),
        new JournalLineData($f['income']->id, 'CR', Money::of(10000, Currency::USD)),
    ]));

    $this->assertDatabaseHas('financial_audit_log', [
        'event_type' => 'journal_posted',
        'subject_id' => $journal->id,
    ]);
});
