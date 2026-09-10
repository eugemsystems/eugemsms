<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\ApproveExchangeRateAction;
use Modules\Finance\Domain\Actions\CalculateRealisedFxAction;
use Modules\Finance\Domain\Actions\CaptureExchangeRateAction;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\Actions\RegisterSchoolCurrencyAction;
use Modules\Finance\Domain\Actions\RejectExchangeRateAction;
use Modules\Finance\Domain\Actions\ReverseFxRevaluationAction;
use Modules\Finance\Domain\Actions\RunFxRevaluationAction;
use Modules\Finance\Domain\Actions\SimulateRateChangeAction;
use Modules\Finance\Domain\DataObjects\ApproveExchangeRateData;
use Modules\Finance\Domain\DataObjects\CalculateRealisedFxData;
use Modules\Finance\Domain\DataObjects\CaptureExchangeRateData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\DataObjects\RegisterSchoolCurrencyData;
use Modules\Finance\Domain\DataObjects\RejectExchangeRateData;
use Modules\Finance\Domain\DataObjects\ReverseFxRevaluationData;
use Modules\Finance\Domain\DataObjects\RunFxRevaluationData;
use Modules\Finance\Domain\DataObjects\SimulateRateChangeData;
use Modules\Finance\Domain\Exceptions\BaseCurrencyImmutableException;
use Modules\Finance\Domain\Exceptions\NoExchangeRateException;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\ExchangeRate;
use Modules\Finance\Models\ExchangeRateSource;
use Modules\Finance\Models\FxRevaluation;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User, manualSource: ExchangeRateSource, approvedSource: ExchangeRateSource}
 */
function fxFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['financial_state' => 'open']);

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}',
    ));

    $manualSource = ExchangeRateSource::factory()->create(['school_id' => null, 'key' => 'manual', 'requires_approval' => false]);
    $approvedSource = ExchangeRateSource::factory()->create(['school_id' => null, 'key' => 'rbz_interbank', 'requires_approval' => true]);

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'user' => User::factory()->create(),
        'manualSource' => $manualSource, 'approvedSource' => $approvedSource,
    ];
}

it('registers a base currency for a school (BR-FIN-06-001)', function (): void {
    $f = fxFixture();

    $base = app(RegisterSchoolCurrencyAction::class)->execute(new RegisterSchoolCurrencyData(
        schoolId: $f['school']->id, currency: 'USD', isBase: true,
    ));

    expect($base->is_base)->toBeTrue();
});

it('refuses to change the base currency once a journal exists (BR-FIN-06-001)', function (): void {
    $f = fxFixture();
    app(RegisterSchoolCurrencyAction::class)->execute(new RegisterSchoolCurrencyData(schoolId: $f['school']->id, currency: 'USD', isBase: true));

    $cash = Account::factory()->for($f['school'])->create(['code' => '1110', 'currency' => 'USD']);
    $income = Account::factory()->for($f['school'])->income()->create(['code' => '4110']);
    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'MANUAL', narration: 'x',
        lines: [new JournalLineData($cash->id, 'DR', Money::of(1000, Currency::USD)), new JournalLineData($income->id, 'CR', Money::of(1000, Currency::USD))],
        effectiveAt: now(), postedByUserId: $f['user']->id,
    ));

    app(RegisterSchoolCurrencyAction::class)->execute(new RegisterSchoolCurrencyData(schoolId: $f['school']->id, currency: 'ZWG', isBase: true));
})->throws(BaseCurrencyImmutableException::class);

it('captures an immediately-active rate from a source with no approval requirement', function (): void {
    $f = fxFixture();

    $rate = app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['manualSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0294117647',
        effectiveFrom: now()->subDay(), capturedByUserId: $f['user']->id,
    ));

    expect($rate->status)->toBe('active')
        ->and($rate->inverse_rate)->toStartWith('34.');
});

it('captures a pending rate from a source that requires approval and activates it on approval (BR-FIN-06-005)', function (): void {
    $f = fxFixture();

    $rate = app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['approvedSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0294117647',
        effectiveFrom: now()->subDay(), capturedByUserId: $f['user']->id,
    ));

    expect($rate->status)->toBe('pending');

    $approved = app(ApproveExchangeRateAction::class)->execute(new ApproveExchangeRateData(
        exchangeRateId: $rate->id, approvedByUserId: $f['user']->id,
    ));

    expect($approved->status)->toBe('active')
        ->and($approved->approved_by)->toBe($f['user']->id);
});

it('rejects a pending rate, leaving it inactive', function (): void {
    $f = fxFixture();

    $rate = app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['approvedSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.03', effectiveFrom: now(), capturedByUserId: $f['user']->id,
    ));

    $rejected = app(RejectExchangeRateAction::class)->execute(new RejectExchangeRateData(
        exchangeRateId: $rate->id, rejectedByUserId: $f['user']->id, reason: 'Implausible rate — likely a typo.',
    ));

    expect($rejected->status)->toBe('rejected');
});

it('supersedes the prior active rate instead of editing it in place (BR-FIN-06-004/AC-FIN-06-003)', function (): void {
    $f = fxFixture();

    $original = app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['manualSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0303030303',
        effectiveFrom: now()->subDays(2), capturedByUserId: $f['user']->id,
    ));

    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['manualSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0294117647',
        effectiveFrom: now()->subDay(), capturedByUserId: $f['user']->id,
    ));

    expect($original->fresh()->status)->toBe('superseded')
        ->and($original->fresh()->effective_to)->not->toBeNull()
        ->and(ExchangeRate::where('status', 'active')->count())->toBe(1);
});

it('converts a foreign-currency journal line using the active rate and records the conversion audit (BR-FIN-06-003)', function (): void {
    $f = fxFixture();
    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['manualSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0294117647',
        effectiveFrom: now()->subDay(), capturedByUserId: $f['user']->id,
    ));

    $zwgCash = Account::factory()->for($f['school'])->create(['code' => '1130', 'currency' => 'ZWG']);
    $income = Account::factory()->for($f['school'])->income()->create(['code' => '4120']);

    $journal = app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'MANUAL', narration: 'ZWG sale',
        lines: [
            new JournalLineData($zwgCash->id, 'DR', Money::of(3400000, Currency::ZWG)),
            new JournalLineData($income->id, 'CR', Money::of(3400000, Currency::ZWG)),
        ],
        effectiveAt: now(), postedByUserId: $f['user']->id,
    ));

    $cashLine = $journal->lines->firstWhere('account_id', $zwgCash->id);
    expect($cashLine->base_currency)->toBe('USD')
        ->and($cashLine->base_amount_minor)->toBe(100000);

    $this->assertDatabaseHas('currency_conversions', [
        'journal_line_id' => $cashLine->id,
        'from_currency' => 'ZWG',
        'to_currency' => 'USD',
        'to_amount_minor' => 100000,
    ]);
});

it('resolves a rate via its inverse pair when only the inverse is registered', function (): void {
    $f = fxFixture();
    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['manualSource']->id,
        fromCurrency: 'USD', toCurrency: 'ZWG', rate: '34.0000000000',
        effectiveFrom: now()->subDay(), capturedByUserId: $f['user']->id,
    ));

    $zwgCash = Account::factory()->for($f['school'])->create(['code' => '1130', 'currency' => 'ZWG']);
    $income = Account::factory()->for($f['school'])->income()->create(['code' => '4120']);

    $journal = app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'MANUAL', narration: 'ZWG sale via inverse rate',
        lines: [
            new JournalLineData($zwgCash->id, 'DR', Money::of(3400000, Currency::ZWG)),
            new JournalLineData($income->id, 'CR', Money::of(3400000, Currency::ZWG)),
        ],
        effectiveAt: now(), postedByUserId: $f['user']->id,
    ));

    expect($journal->lines->firstWhere('account_id', $zwgCash->id)->base_amount_minor)->toBe(100000);
});

it('refuses to post a foreign-currency line with no rate for that date, writing nothing (BR-FIN-06-007/AC-FIN-06-002)', function (): void {
    $f = fxFixture();
    $zwgCash = Account::factory()->for($f['school'])->create(['code' => '1130', 'currency' => 'ZWG']);
    $income = Account::factory()->for($f['school'])->income()->create(['code' => '4120']);

    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'MANUAL', narration: 'No rate exists',
        lines: [
            new JournalLineData($zwgCash->id, 'DR', Money::of(3400000, Currency::ZWG)),
            new JournalLineData($income->id, 'CR', Money::of(3400000, Currency::ZWG)),
        ],
        effectiveAt: now(), postedByUserId: $f['user']->id,
    ));
})->throws(NoExchangeRateException::class);

it('uses the rate effective on a backdated transaction date, not the rate at entry time (BR-FIN-06-008/AC-FIN-06-006)', function (): void {
    $f = fxFixture();
    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['manualSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0303030303',
        effectiveFrom: now()->subDays(10), capturedByUserId: $f['user']->id,
    ));
    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['manualSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0294117647',
        effectiveFrom: now()->subDays(1), capturedByUserId: $f['user']->id,
    ));

    $zwgCash = Account::factory()->for($f['school'])->create(['code' => '1130', 'currency' => 'ZWG']);
    $income = Account::factory()->for($f['school'])->income()->create(['code' => '4120']);

    // Backdated into the window covered by the FIRST rate (33.00), even
    // though the SECOND (34.00) is active today.
    $journal = app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'MANUAL', narration: 'Backdated',
        lines: [
            new JournalLineData($zwgCash->id, 'DR', Money::of(3300000, Currency::ZWG)),
            new JournalLineData($income->id, 'CR', Money::of(3300000, Currency::ZWG)),
        ],
        effectiveAt: now()->subDays(5), postedByUserId: $f['user']->id,
    ));

    expect($journal->lines->firstWhere('account_id', $zwgCash->id)->base_amount_minor)->toBe(100000);
});

it('calculates a realised FX loss on settlement matching the worked example (Book B FIN-06 §3/BR-FIN-06-009)', function (): void {
    $f = fxFixture();
    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['manualSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0294117647',
        effectiveFrom: now()->subDay(), capturedByUserId: $f['user']->id,
    ));

    $result = app(CalculateRealisedFxAction::class)->execute(new CalculateRealisedFxData(
        schoolId: $f['school']->id,
        obligationReliefAmount: Money::of(46364, Currency::USD),
        tenderedAmount: Money::of(1530000, Currency::ZWG),
        settlementDate: now(),
    ));

    expect($result->bankBaseAmount->minor)->toBe(45000)
        ->and($result->isLoss())->toBeTrue()
        ->and($result->differenceMinor())->toBe(-1364);
});

it('runs an FX revaluation, restating a ZWG debtor and posting the unrealised loss (BR-FIN-06-010..012)', function (): void {
    $f = fxFixture();
    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['manualSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0303030303',
        effectiveFrom: now()->subDays(10), capturedByUserId: $f['user']->id,
    ));

    $zwgDebtors = Account::factory()->for($f['school'])->controlAccount('learner')->create(['code' => '1220']);
    $zwgIncome = Account::factory()->for($f['school'])->income()->create(['code' => '4130']);
    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'FEE_BILLING', narration: 'ZWG levy',
        lines: [
            new JournalLineData($zwgDebtors->id, 'DR', Money::of(2400000, Currency::ZWG), subledgerType: 'learner', subledgerId: 9),
            new JournalLineData($zwgIncome->id, 'CR', Money::of(2400000, Currency::ZWG)),
        ],
        effectiveAt: now()->subDays(9), postedByUserId: $f['user']->id,
    ));

    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['manualSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0289855072',
        effectiveFrom: now()->subDay(), capturedByUserId: $f['user']->id,
    ));

    $fxLossAccount = Account::factory()->for($f['school'])->expense()->system('fx_unrealised_loss')->create(['code' => '5930']);
    Account::factory()->for($f['school'])->income()->system('fx_unrealised_gain')->create(['code' => '4920']);

    $revaluation = app(RunFxRevaluationAction::class)->execute(new RunFxRevaluationData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        revaluationDate: now(), performedByUserId: $f['user']->id,
    ));

    expect($revaluation->status)->toBe('posted')
        ->and($revaluation->loss_minor)->toBeGreaterThan(0)
        ->and($revaluation->journal_id)->not->toBeNull();

    $journal = $revaluation->journal;
    expect($journal->lines->firstWhere('account_id', $fxLossAccount->id))->not->toBeNull()
        ->and($journal->lines->firstWhere('account_id', $zwgDebtors->id)->direction)->toBe('CR');
});

it('reverses a posted FX revaluation through a full journal reversal, never deletion (BR-FIN-06-012)', function (): void {
    $f = fxFixture();
    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['manualSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0303030303',
        effectiveFrom: now()->subDays(10), capturedByUserId: $f['user']->id,
    ));
    $zwgDebtors = Account::factory()->for($f['school'])->controlAccount('learner')->create(['code' => '1220']);
    $zwgIncome = Account::factory()->for($f['school'])->income()->create(['code' => '4130']);
    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'FEE_BILLING', narration: 'ZWG levy',
        lines: [
            new JournalLineData($zwgDebtors->id, 'DR', Money::of(2400000, Currency::ZWG), subledgerType: 'learner', subledgerId: 9),
            new JournalLineData($zwgIncome->id, 'CR', Money::of(2400000, Currency::ZWG)),
        ],
        effectiveAt: now()->subDays(9), postedByUserId: $f['user']->id,
    ));
    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['manualSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0289855072',
        effectiveFrom: now()->subDay(), capturedByUserId: $f['user']->id,
    ));
    Account::factory()->for($f['school'])->expense()->system('fx_unrealised_loss')->create(['code' => '5930']);
    Account::factory()->for($f['school'])->income()->system('fx_unrealised_gain')->create(['code' => '4920']);

    $revaluation = app(RunFxRevaluationAction::class)->execute(new RunFxRevaluationData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        revaluationDate: now(), performedByUserId: $f['user']->id,
    ));

    $reversed = app(ReverseFxRevaluationAction::class)->execute(new ReverseFxRevaluationData(
        revaluationId: $revaluation->id, reason: 'Rate correction required a redo.', reversedByUserId: $f['user']->id,
    ));

    expect($reversed->status)->toBe('reversed')
        ->and($revaluation->journal->fresh()->reversed_by_journal_id)->not->toBeNull();
});

it('simulates a proposed rate change without persisting anything (BR-FIN-06-013/AC-FIN-06-005)', function (): void {
    $f = fxFixture();
    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $f['manualSource']->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0303030303',
        effectiveFrom: now()->subDay(), capturedByUserId: $f['user']->id,
    ));
    $zwgDebtors = Account::factory()->for($f['school'])->controlAccount('learner')->create(['code' => '1220']);
    $zwgIncome = Account::factory()->for($f['school'])->income()->create(['code' => '4130']);
    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'FEE_BILLING', narration: 'ZWG levy',
        lines: [
            new JournalLineData($zwgDebtors->id, 'DR', Money::of(2400000, Currency::ZWG), subledgerType: 'learner', subledgerId: 9),
            new JournalLineData($zwgIncome->id, 'CR', Money::of(2400000, Currency::ZWG)),
        ],
        effectiveAt: now(), postedByUserId: $f['user']->id,
    ));

    $simulation = app(SimulateRateChangeAction::class)->execute(new SimulateRateChangeData(
        schoolId: $f['school']->id, foreignCurrency: 'ZWG', proposedRate: '0.0289855072', asAt: now(),
    ));

    expect($simulation->totalDebtorsRecordedMinor)->toBeGreaterThan(0)
        ->and($simulation->fxResultMinor)->not->toBe(0);

    expect(FxRevaluation::count())->toBe(0);
});
