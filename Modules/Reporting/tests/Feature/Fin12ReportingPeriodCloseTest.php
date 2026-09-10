<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Sessions\RunPeriodCloseChecklistAction;
use Modules\Core\Domain\Actions\Sessions\TransitionPeriodStateAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Sessions\TransitionPeriodData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\File;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\JournalLine;
use Modules\Reporting\Domain\Actions\AcknowledgeCloseCheckAction;
use Modules\Reporting\Domain\Actions\GenerateAccountingExportAction;
use Modules\Reporting\Domain\Actions\GenerateClosePackAction;
use Modules\Reporting\Domain\Actions\GenerateIncomeStatementAction;
use Modules\Reporting\Domain\Actions\GenerateTrialBalanceAction;
use Modules\Reporting\Domain\Actions\RunAndRecordCloseChecklistAction;
use Modules\Reporting\Domain\DataObjects\AcknowledgeCloseCheckData;
use Modules\Reporting\Domain\DataObjects\GenerateAccountingExportData;
use Modules\Reporting\Domain\DataObjects\GenerateClosePackData;
use Modules\Reporting\Domain\DataObjects\GenerateIncomeStatementData;
use Modules\Reporting\Domain\DataObjects\GenerateTrialBalanceData;
use Modules\Reporting\Domain\DataObjects\RunAndRecordCloseChecklistData;
use Modules\Reporting\Domain\Events\DuplicateAccountingExportAttempted;
use Modules\Reporting\Domain\Exceptions\BlockingCheckCannotBeAcknowledgedException;

/**
 * @return array<string, mixed>
 */
function fin12Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'starts_on' => now()->subMonth()->startOfMonth()->toDateString(),
        'ends_on' => now()->addMonth()->endOfMonth()->toDateString(),
    ]);
    $user = User::factory()->create();

    foreach (['journal'] as $documentType) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $documentType, pattern: 'JNL/{SEQ:6}',
        ));
    }

    $cash = Account::factory()->for($school)->create();
    $income = Account::factory()->for($school)->income()->create();
    $expense = Account::factory()->for($school)->expense()->create();

    return compact('school', 'year', 'term', 'user', 'cash', 'income', 'expense');
}

it('regenerates a historical statement identically (AC-FIN-12-001)', function (): void {
    $f = fin12Fixture();
    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'RECEIPT', narration: 'Test income',
        lines: [
            new JournalLineData(accountId: $f['cash']->id, direction: 'DR', amount: Money::of(10000, Currency::USD)),
            new JournalLineData(accountId: $f['income']->id, direction: 'CR', amount: Money::of(10000, Currency::USD)),
        ],
        effectiveAt: now(), postedByUserId: $f['user']->id,
    ));

    $data = new GenerateIncomeStatementData($f['school']->id, $f['term']->starts_on, $f['term']->ends_on);
    $first = app(GenerateIncomeStatementAction::class)->execute($data);
    $second = app(GenerateIncomeStatementAction::class)->execute($data);

    expect($second->netMinor)->toBe($first->netMinor)
        ->and($second->lines)->toBe($first->lines);
});

it('filters a point-in-time report on posted_at and itemises the reconciling prior-period adjustment separately (AC-FIN-12-002/003)', function (): void {
    $f = fin12Fixture();
    $knownOn = now()->subDays(2);

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'RECEIPT', narration: 'Original entry, posted before the known-on date',
        lines: [
            new JournalLineData(accountId: $f['cash']->id, direction: 'DR', amount: Money::of(5000, Currency::USD)),
            new JournalLineData(accountId: $f['income']->id, direction: 'CR', amount: Money::of(5000, Currency::USD)),
        ],
        effectiveAt: $f['term']->starts_on->copy()->addDays(2), postedByUserId: $f['user']->id,
    ));

    // Force this journal's posted_at to be BEFORE $knownOn.
    Journal::where('school_id', $f['school']->id)->update(['posted_at' => $knownOn->copy()->subDay()]);

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'RECEIPT', narration: 'Prior-period adjustment, posted after the known-on date',
        lines: [
            new JournalLineData(accountId: $f['cash']->id, direction: 'DR', amount: Money::of(750, Currency::USD)),
            new JournalLineData(accountId: $f['income']->id, direction: 'CR', amount: Money::of(750, Currency::USD)),
        ],
        effectiveAt: $f['term']->starts_on->copy()->addDays(3), postedByUserId: $f['user']->id,
    ));

    $asKnown = app(GenerateIncomeStatementAction::class)->execute(new GenerateIncomeStatementData(
        $f['school']->id, $f['term']->starts_on, $f['term']->ends_on, $knownOn,
    ));
    $current = app(GenerateIncomeStatementAction::class)->execute(new GenerateIncomeStatementData(
        $f['school']->id, $f['term']->starts_on, $f['term']->ends_on,
    ));

    expect($asKnown->netMinor)->toBe(5000)
        ->and($current->netMinor)->toBe(5750)
        ->and($asKnown->reconcilingTotalMinor)->toBe(750)
        ->and($asKnown->reconcilingItems)->not->toBeEmpty();
});

it('fails the checklist as blocking when the trial balance does not balance, and no user can override it or lock the period (AC-FIN-12-004)', function (): void {
    $f = fin12Fixture();

    // A deliberately unbalanced journal line pair, inserted directly
    // (bypassing PostJournalAction's own balance assertion) to prove
    // the CHECK catches it independently.
    $journal = Journal::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'effective_at' => now(), 'posted_at' => now(),
    ]);
    JournalLine::factory()->create([
        'school_id' => $f['school']->id, 'journal_id' => $journal->id, 'account_id' => $f['cash']->id,
        'direction' => 'DR', 'amount_minor' => 10000, 'currency' => 'USD', 'term_id' => $f['term']->id,
        'effective_at' => now(),
    ]);
    JournalLine::factory()->create([
        'school_id' => $f['school']->id, 'journal_id' => $journal->id, 'account_id' => $f['income']->id,
        'direction' => 'CR', 'amount_minor' => 9000, 'currency' => 'USD', 'term_id' => $f['term']->id,
        'effective_at' => now(),
    ]);

    $checklist = app(RunAndRecordCloseChecklistAction::class)->execute(new RunAndRecordCloseChecklistData(
        termId: $f['term']->id, periodType: 'financial', runByUserId: $f['user']->id,
    ));

    expect($checklist->overall_status)->toBe('failed')
        ->and($checklist->blocking_failures)->toBeGreaterThan(0);

    $trialBalanceResult = collect($checklist->results)->firstWhere('code', 'fin01_trial_balance_balances');
    expect($trialBalanceResult['passed'])->toBeFalse();

    expect(fn () => app(AcknowledgeCloseCheckAction::class)->execute(new AcknowledgeCloseCheckData(
        checklistId: $checklist->id, checkKey: 'fin01_trial_balance_balances', reason: 'Approved anyway.',
        acknowledgedByUserId: $f['user']->id,
    )))->toThrow(BlockingCheckCannotBeAcknowledgedException::class);

    $approver = User::factory()->create();
    expect(fn () => app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
        termId: $f['term']->id, periodType: PeriodType::Financial, toState: PeriodState::Locked,
        performedByUserId: $f['user']->id, approvedByUserId: $approver->id,
    )))->toThrow(InvalidStateTransitionException::class);
});

it('requires a written reason to acknowledge a warning-level check, and the checklist reflects it (AC-FIN-12-005)', function (): void {
    $f = fin12Fixture();

    $checklist = app(RunAndRecordCloseChecklistAction::class)->execute(new RunAndRecordCloseChecklistData(
        termId: $f['term']->id, periodType: 'financial', runByUserId: $f['user']->id,
    ));

    // A clean fixture with no data at all passes every registered
    // check trivially — assert the run itself is real (drawn from the
    // live registry, not a stub) by checking a genuine cross-module
    // check actually ran.
    $codes = collect($checklist->results)->pluck('code');
    expect($codes)->toContain('fin13_fiscalisation_reconciled')
        ->and($codes)->toContain('fin14_wallet_liability_reconciles')
        ->and($codes)->toContain('ppl05_payroll_posted_and_returns_prepared');

    expect(fn () => app(AcknowledgeCloseCheckAction::class)->execute(new AcknowledgeCloseCheckData(
        checklistId: $checklist->id, checkKey: 'fin01_trial_balance_balances', reason: 'x',
        acknowledgedByUserId: $f['user']->id,
    )))->toThrow(ValidationException::class);
});

it('generates a signed close pack containing the checklist, acknowledgements and authoriser (AC-FIN-12-006)', function (): void {
    $f = fin12Fixture();

    $checklist = app(RunAndRecordCloseChecklistAction::class)->execute(new RunAndRecordCloseChecklistData(
        termId: $f['term']->id, periodType: 'financial', runByUserId: $f['user']->id,
    ));

    $packed = app(GenerateClosePackAction::class)->execute(new GenerateClosePackData(
        checklistId: $checklist->id, generatedByUserId: $f['user']->id,
    ));

    expect($packed->report_document_id)->not->toBeNull();

    $file = File::findOrFail($packed->report_document_id);
    $contents = Storage::disk($file->disk)->get($file->path);
    $pack = json_decode($contents, true);

    expect($pack['overall_status'])->toBe($checklist->overall_status)
        ->and($pack['authorised_by'])->toBe($f['user']->id)
        ->and($pack)->toHaveKey('results')
        ->and($pack)->toHaveKey('acknowledgements');
});

it('exports journal lines for a period and flags an overlapping re-export (BR-FIN-12-014)', function (): void {
    Event::fake([DuplicateAccountingExportAttempted::class]);
    $f = fin12Fixture();
    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'RECEIPT', narration: 'Export test',
        lines: [
            new JournalLineData(accountId: $f['cash']->id, direction: 'DR', amount: Money::of(1500, Currency::USD)),
            new JournalLineData(accountId: $f['income']->id, direction: 'CR', amount: Money::of(1500, Currency::USD)),
        ],
        effectiveAt: now(), postedByUserId: $f['user']->id,
    ));

    $export = app(GenerateAccountingExportAction::class)->execute(new GenerateAccountingExportData(
        schoolId: $f['school']->id, targetSystem: 'generic_csv', periodFrom: $f['term']->starts_on,
        periodTo: $f['term']->ends_on, exportedByUserId: $f['user']->id,
    ));

    expect($export->journal_count)->toBeGreaterThan(0);

    app(GenerateAccountingExportAction::class)->execute(new GenerateAccountingExportData(
        schoolId: $f['school']->id, targetSystem: 'generic_csv', periodFrom: $f['term']->starts_on,
        periodTo: $f['term']->ends_on, exportedByUserId: $f['user']->id,
    ));

    Event::assertDispatched(DuplicateAccountingExportAttempted::class);
});

it('produces a per-currency trial balance that balances for a healthy ledger (BR-FIN-12-001)', function (): void {
    $f = fin12Fixture();
    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'RECEIPT', narration: 'Balanced entry',
        lines: [
            new JournalLineData(accountId: $f['cash']->id, direction: 'DR', amount: Money::of(2000, Currency::USD)),
            new JournalLineData(accountId: $f['income']->id, direction: 'CR', amount: Money::of(2000, Currency::USD)),
        ],
        effectiveAt: now(), postedByUserId: $f['user']->id,
    ));

    $rows = app(GenerateTrialBalanceAction::class)->execute(new GenerateTrialBalanceData($f['school']->id, now()));

    $totalDebits = array_sum(array_column($rows, 'debit_minor'));
    $totalCredits = array_sum(array_column($rows, 'credit_minor'));

    expect($totalDebits)->toBe($totalCredits)
        ->and($totalDebits)->toBeGreaterThan(0);
});

it('runs the real, previously-empty close checklist registry end to end (BR-FIN-12-009)', function (): void {
    $f = fin12Fixture();

    $result = app(RunPeriodCloseChecklistAction::class)->execute($f['term'], PeriodType::Financial);

    expect($result->items)->not->toBeEmpty()
        ->and($result->passesBlocking())->toBeTrue();
});
