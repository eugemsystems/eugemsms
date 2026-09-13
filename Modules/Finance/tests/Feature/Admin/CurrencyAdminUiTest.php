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
use Modules\Finance\Domain\Actions\CaptureExchangeRateAction;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\CaptureExchangeRateData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Livewire\Currency\ApproveRate;
use Modules\Finance\Livewire\Currency\CaptureRate;
use Modules\Finance\Livewire\Currency\ConversionLog;
use Modules\Finance\Livewire\Currency\Index as CurrencyIndex;
use Modules\Finance\Livewire\Currency\Rates;
use Modules\Finance\Livewire\Currency\Revaluation;
use Modules\Finance\Livewire\Currency\Simulate;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\ExchangeRate;
use Modules\Finance\Models\ExchangeRateSource;
use Modules\Finance\Models\FxRevaluation;
use Modules\Finance\Models\SchoolCurrency;

/**
 * Book B FIN-06 §6 admin UI. Reuses the same permission-grant discipline
 * as `GeneralLedgerAdminUiTest` (a Pest helper defined in one test file
 * is not callable from another run standalone — see .ai/rules/tests.md
 * — so this file carries its own, distinctly-named fixture rather than
 * relying on that file's).
 *
 * @return array{school: School, year: AcademicYear, term: Term}
 */
function currencyAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id,
        documentType: 'journal',
        pattern: 'JNL/{SEQ:6}',
    ));

    return ['school' => $school, 'year' => $year, 'term' => $term];
}

function currencyAdminUser(School $school, string ...$permissionNames): User
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

beforeEach(function (): void {
    if (! Route::has('finance.currency.index')) {
        require base_path('Modules/Finance/routes/currency.php');
    }
});

it('registers and edits a school currency (BR-FIN-06-001)', function (): void {
    $f = currencyAdminFixture();
    $user = currencyAdminUser($f['school'], 'finance.currency.manage');

    Livewire::actingAs($user)
        ->test(CurrencyIndex::class, ['school' => $f['school']])
        ->call('openEditModal')
        ->set('currency', 'USD')
        ->set('isBase', true)
        ->call('save')
        ->assertHasNoErrors();

    $schoolCurrency = SchoolCurrency::where('school_id', $f['school']->id)->where('currency', 'USD')->sole();
    expect($schoolCurrency->is_base)->toBeTrue();

    Livewire::actingAs($user)
        ->test(CurrencyIndex::class, ['school' => $f['school']])
        ->call('openEditModal', 'USD')
        ->assertSet('isBase', true)
        ->set('roundingIncrementMinor', 25)
        ->call('save')
        ->assertHasNoErrors();

    expect($schoolCurrency->fresh()->rounding_increment_minor)->toBe(25);
});

it('lists captured exchange rates (append-only history)', function (): void {
    $f = currencyAdminFixture();
    $user = currencyAdminUser($f['school'], 'finance.rate.view');
    $source = ExchangeRateSource::factory()->create(['school_id' => null, 'key' => 'manual_test', 'requires_approval' => false]);

    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id,
        sourceId: $source->id,
        fromCurrency: 'ZWG',
        toCurrency: 'USD',
        rate: '0.0294117647',
        effectiveFrom: now()->subDay(),
        capturedByUserId: $user->id,
    ));

    Livewire::actingAs($user)
        ->test(Rates::class, ['school' => $f['school']])
        ->assertOk()
        ->assertSee('ZWG')
        ->assertSee('0.0294117647');
});

it('captures a new exchange rate, landing pending or active per the source (BR-FIN-06-004/005)', function (): void {
    $f = currencyAdminFixture();
    $user = currencyAdminUser($f['school'], 'finance.rate.capture');
    $immediateSource = ExchangeRateSource::factory()->create(['school_id' => null, 'key' => 'manual_immediate', 'requires_approval' => false]);

    Livewire::actingAs($user)
        ->test(CaptureRate::class, ['school' => $f['school']])
        ->set('sourceId', $immediateSource->id)
        ->set('fromCurrency', 'ZWG')
        ->set('toCurrency', 'USD')
        ->set('rate', '0.03')
        ->set('effectiveFrom', now()->subDay()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $rate = ExchangeRate::where('school_id', $f['school']->id)->sole();
    expect($rate->status)->toBe('active');
});

it('shows the impact simulation for a pending rate before it can be approved, and rejects one with a reason (BR-FIN-06-005/013)', function (): void {
    $f = currencyAdminFixture();
    $user = currencyAdminUser($f['school'], 'finance.rate.approve');
    $reviewedSource = ExchangeRateSource::factory()->create(['school_id' => null, 'key' => 'manual_reviewed', 'requires_approval' => true]);

    // Debtors carrying a foreign balance so the impact simulation has
    // something to show — needs an already-active rate to post against.
    $activeSource = ExchangeRateSource::factory()->create(['school_id' => null, 'key' => 'manual_active', 'requires_approval' => false]);
    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $activeSource->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0303030303',
        effectiveFrom: now()->subDays(10), capturedByUserId: $user->id,
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
        effectiveAt: now()->subDays(9), postedByUserId: $user->id,
    ));

    $pendingRate = app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $reviewedSource->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0289855072',
        effectiveFrom: now(), capturedByUserId: $user->id,
    ));

    expect($pendingRate->status)->toBe('pending');

    Livewire::actingAs($user)
        ->test(ApproveRate::class, ['school' => $f['school']])
        ->call('simulate', $pendingRate->id)
        ->assertSee('FX result');

    $secondPendingRate = app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $reviewedSource->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0250',
        effectiveFrom: now()->addHour(), capturedByUserId: $user->id,
    ));

    Livewire::actingAs($user)
        ->test(ApproveRate::class, ['school' => $f['school']])
        ->call('openReject', $secondPendingRate->id)
        ->set('rejectReason', 'Implausible — clearly a data entry error.')
        ->call('reject')
        ->assertHasNoErrors();

    expect($secondPendingRate->fresh()->status)->toBe('rejected');

    Livewire::actingAs($user)
        ->test(ApproveRate::class, ['school' => $f['school']])
        ->call('approve', $pendingRate->id)
        ->assertDispatched('toast');

    expect($pendingRate->fresh()->status)->toBe('active');
});

it('previews a proposed rate change without persisting anything (BR-FIN-06-013/AC-FIN-06-005)', function (): void {
    $f = currencyAdminFixture();
    $user = currencyAdminUser($f['school'], 'finance.rate.view');
    $source = ExchangeRateSource::factory()->create(['school_id' => null, 'key' => 'manual_sim', 'requires_approval' => false]);

    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $source->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0303030303',
        effectiveFrom: now()->subDay(), capturedByUserId: $user->id,
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
        effectiveAt: now(), postedByUserId: $user->id,
    ));

    Livewire::actingAs($user)
        ->test(Simulate::class, ['school' => $f['school']])
        ->set('foreignCurrency', 'ZWG')
        ->set('proposedRate', '0.0289855072')
        ->call('preview')
        ->assertHasNoErrors()
        ->assertSee('FX result');

    expect(FxRevaluation::count())->toBe(0);
});

it('runs a period-end FX revaluation and reverses it through a full journal reversal (BR-FIN-06-010..012)', function (): void {
    $f = currencyAdminFixture();
    $user = currencyAdminUser($f['school'], 'finance.fx.revalue');
    $source = ExchangeRateSource::factory()->create(['school_id' => null, 'key' => 'manual_reval', 'requires_approval' => false]);

    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $source->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0303030303',
        effectiveFrom: now()->subDays(10), capturedByUserId: $user->id,
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
        effectiveAt: now()->subDays(9), postedByUserId: $user->id,
    ));
    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $source->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0289855072',
        effectiveFrom: now()->subDay(), capturedByUserId: $user->id,
    ));
    Account::factory()->for($f['school'])->expense()->system('fx_unrealised_loss')->create(['code' => '5930']);
    Account::factory()->for($f['school'])->income()->system('fx_unrealised_gain')->create(['code' => '4920']);

    Livewire::actingAs($user)
        ->test(Revaluation::class, ['school' => $f['school']])
        ->set('revaluationDate', now()->toDateString())
        ->call('run')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $revaluation = FxRevaluation::where('school_id', $f['school']->id)->sole();
    expect($revaluation->status)->toBe('posted');

    Livewire::actingAs($user)
        ->test(Revaluation::class, ['school' => $f['school']])
        ->call('openReverse', $revaluation->id)
        ->set('reverseReason', 'Rate correction required a redo of this revaluation.')
        ->call('reverse')
        ->assertHasNoErrors();

    expect($revaluation->fresh()->status)->toBe('reversed');
});

it('lists the currency conversion audit log (BR-FIN-06-003)', function (): void {
    $f = currencyAdminFixture();
    $user = currencyAdminUser($f['school'], 'finance.rate.view');
    $source = ExchangeRateSource::factory()->create(['school_id' => null, 'key' => 'manual_log', 'requires_approval' => false]);

    app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
        schoolId: $f['school']->id, sourceId: $source->id,
        fromCurrency: 'ZWG', toCurrency: 'USD', rate: '0.0294117647',
        effectiveFrom: now()->subDay(), capturedByUserId: $user->id,
    ));

    $zwgCash = Account::factory()->for($f['school'])->create(['code' => '1130', 'currency' => 'ZWG']);
    $income = Account::factory()->for($f['school'])->income()->create(['code' => '4120']);
    app(PostJournalAction::class)->execute(new PostJournalData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        journalType: 'MANUAL', narration: 'ZWG sale',
        lines: [
            new JournalLineData($zwgCash->id, 'DR', Money::of(3400000, Currency::ZWG)),
            new JournalLineData($income->id, 'CR', Money::of(3400000, Currency::ZWG)),
        ],
        effectiveAt: now(), postedByUserId: $user->id,
    ));

    Livewire::actingAs($user)
        ->test(ConversionLog::class, ['school' => $f['school']])
        ->assertOk()
        ->assertSee('ZWG')
        ->assertSee('USD');
});

/**
 * Real routed GET, not `Livewire::test()` — see the identical test in
 * `GeneralLedgerAdminUiTest` for why this matters specifically for a
 * class literally named `Index` (`Currency\Index` here).
 */
it('serves Finance\Currency\Index through a real routed request (Livewire implicit-binding gotcha)', function (): void {
    $f = currencyAdminFixture();
    $user = currencyAdminUser($f['school'], 'finance.currency.manage');

    $this->actingAs($user)->get(route('finance.currency.index', $f['school']))->assertOk();
});
