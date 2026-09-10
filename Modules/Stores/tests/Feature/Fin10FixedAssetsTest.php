<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\RebuildAccountBalancesAction;
use Modules\Finance\Domain\DataObjects\RebuildAccountBalancesData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\Journal;
use Modules\Stores\Domain\Actions\ApproveDepreciationRunAction;
use Modules\Stores\Domain\Actions\CapitalizeAssetAction;
use Modules\Stores\Domain\Actions\CheckUnderInsuranceAction;
use Modules\Stores\Domain\Actions\CreateVerificationRoundAction;
use Modules\Stores\Domain\Actions\DisposeAssetAction;
use Modules\Stores\Domain\Actions\PostDepreciationRunAction;
use Modules\Stores\Domain\Actions\PreviewDepreciationRunAction;
use Modules\Stores\Domain\Actions\ReconcileAssetRegisterAction;
use Modules\Stores\Domain\Actions\RecordAssetVerificationScanAction;
use Modules\Stores\Domain\Actions\RecordInsurancePolicyAction;
use Modules\Stores\Domain\Actions\TransferAssetAction;
use Modules\Stores\Domain\Actions\WriteOffNotFoundAssetAction;
use Modules\Stores\Domain\DataObjects\CapitalizeAssetData;
use Modules\Stores\Domain\DataObjects\DisposeAssetData;
use Modules\Stores\Domain\DataObjects\PreviewDepreciationRunData;
use Modules\Stores\Domain\DataObjects\RecordInsurancePolicyData;
use Modules\Stores\Domain\Events\AssetNotFound;
use Modules\Stores\Domain\Events\RegisterLedgerDivergence;
use Modules\Stores\Domain\Events\UnderInsuranceDetected;
use Modules\Stores\Domain\Exceptions\DisposalRequiresApprovalException;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\AssetVerification;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User, user2: User, costCentre: CostCentre, category: AssetCategory, contra: Account}
 */
function fin10Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['financial_state' => 'open']);
    $user = User::factory()->create();
    $user2 = User::factory()->create();

    foreach (['journal', 'fixed_asset'] as $type) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $type, pattern: strtoupper(substr($type, 0, 3)).'/{SEQ:5}',
        ));
    }

    $costCentre = CostCentre::factory()->for($school)->create();
    $assetAccount = Account::factory()->for($school)->create(['code' => 'ASSET-ICT']);
    $accumDepAccount = Account::factory()->for($school)->create(['code' => 'ACCDEP-ICT']);
    $depExpenseAccount = Account::factory()->for($school)->expense()->create(['code' => 'DEPEXP-ICT']);
    $disposalAccount = Account::factory()->for($school)->create(['code' => 'DISPOSAL-ICT']);
    $contra = Account::factory()->for($school)->create(['code' => 'AP-CLEARING']);

    $category = AssetCategory::factory()->create([
        'school_id' => $school->id, 'code' => 'ICT', 'asset_account_id' => $assetAccount->id,
        'accum_depreciation_account_id' => $accumDepAccount->id, 'depreciation_expense_account_id' => $depExpenseAccount->id,
        'disposal_account_id' => $disposalAccount->id,
    ]);

    return compact('school', 'year', 'term', 'user', 'user2', 'costCentre', 'category', 'contra');
}

it('capitalises a purchased asset at cost, posting Dr Fixed Assets / Cr the contra account (BR-FIN-10-002)', function (): void {
    $f = fin10Fixture();

    $asset = app(CapitalizeAssetAction::class)->execute(new CapitalizeAssetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        categoryId: $f['category']->id, name: 'Dell Latitude Laptop', acquisitionDate: now(),
        acquisitionCostMinor: 1200000, currency: 'USD', acquisitionSource: 'purchase',
        costCentreId: $f['costCentre']->id, contraAccountId: $f['contra']->id, performedByUserId: $f['user']->id,
    ));

    expect($asset->status)->toBe('active')
        ->and($asset->net_book_value_minor)->toBe(1200000)
        ->and($asset->asset_tag)->toStartWith('FIX/');

    $journal = Journal::where('source_type', 'fixed_asset')->where('source_id', $asset->id)->first();
    expect($journal->lines->firstWhere('direction', 'DR')->account_id)->toBe($f['category']->asset_account_id)
        ->and($journal->lines->firstWhere('direction', 'CR')->account_id)->toBe($f['contra']->id);
});

it('records a donated asset at fair value with the donor named (BR-FIN-10-003)', function (): void {
    $f = fin10Fixture();
    $donationIncome = Account::factory()->for($f['school'])->income()->create();

    $asset = app(CapitalizeAssetAction::class)->execute(new CapitalizeAssetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        categoryId: $f['category']->id, name: 'Donated Minibus', acquisitionDate: now(),
        acquisitionCostMinor: 8000000, currency: 'USD', acquisitionSource: 'donation',
        costCentreId: $f['costCentre']->id, contraAccountId: $donationIncome->id, performedByUserId: $f['user']->id,
        donorName: 'Old Boys Association',
    ));

    expect($asset->acquisition_source)->toBe('donation')
        ->and($asset->donor_name)->toBe('Old Boys Association');
});

it('depreciates a straight-line asset to exactly the residual value over its useful life (AC-FIN-10-001)', function (): void {
    $f = fin10Fixture();
    $asset = app(CapitalizeAssetAction::class)->execute(new CapitalizeAssetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        categoryId: $f['category']->id, name: 'Straight Line Asset', acquisitionDate: now()->subMonths(60)->startOfMonth(),
        acquisitionCostMinor: 1200000, currency: 'USD', acquisitionSource: 'purchase',
        costCentreId: $f['costCentre']->id, contraAccountId: $f['contra']->id, performedByUserId: $f['user']->id,
        residualValueMinor: 200000, usefulLifeYears: 5,
    ));

    // Straight line: (1,200,000 - 200,000) / 60 months = 16,666.67 -> rounds to 16,667.
    // depreciation_start_date casts to CarbonImmutable in this app, so
    // addMonth() must be reassigned rather than relied on to mutate.
    $month = Carbon::parse($asset->depreciation_start_date->toDateString());

    for ($i = 0; $i < 60; $i++) {
        $run = app(PreviewDepreciationRunAction::class)->execute(new PreviewDepreciationRunData(
            schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
            periodMonth: $month->format('Y-m'), computedByUserId: $f['user']->id,
        ));

        if ($run->asset_count > 0) {
            app(ApproveDepreciationRunAction::class)->execute($run->id, $f['user2']->id);
            app(PostDepreciationRunAction::class)->execute($run->id, $f['user2']->id);
        }

        $month = $month->addMonth();
    }

    $asset->refresh();
    expect((int) $asset->net_book_value_minor)->toBe(200000)
        ->and($asset->fully_depreciated)->toBeTrue();
});

it('refuses a second depreciation run for a period already posted (BR-FIN-10-005/AC-FIN-10-002)', function (): void {
    $f = fin10Fixture();
    app(CapitalizeAssetAction::class)->execute(new CapitalizeAssetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        categoryId: $f['category']->id, name: 'Asset', acquisitionDate: now()->subMonths(2)->startOfMonth(),
        acquisitionCostMinor: 600000, currency: 'USD', acquisitionSource: 'purchase',
        costCentreId: $f['costCentre']->id, contraAccountId: $f['contra']->id, performedByUserId: $f['user']->id,
        residualValueMinor: 0, usefulLifeYears: 5,
    ));

    $periodMonth = now()->format('Y-m');
    $run = app(PreviewDepreciationRunAction::class)->execute(new PreviewDepreciationRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        periodMonth: $periodMonth, computedByUserId: $f['user']->id,
    ));
    app(ApproveDepreciationRunAction::class)->execute($run->id, $f['user2']->id);
    app(PostDepreciationRunAction::class)->execute($run->id, $f['user2']->id);

    expect(fn () => app(PreviewDepreciationRunAction::class)->execute(new PreviewDepreciationRunData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        periodMonth: $periodMonth, computedByUserId: $f['user']->id,
    )))->toThrow(ValidationException::class);
});

it('moves an asset to a new cost centre and writes an append-only movement record (BR-FIN-10-009/010)', function (): void {
    $f = fin10Fixture();
    $asset = app(CapitalizeAssetAction::class)->execute(new CapitalizeAssetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        categoryId: $f['category']->id, name: 'Asset', acquisitionDate: now(),
        acquisitionCostMinor: 500000, currency: 'USD', acquisitionSource: 'purchase',
        costCentreId: $f['costCentre']->id, contraAccountId: $f['contra']->id, performedByUserId: $f['user']->id,
    ));
    $newCostCentre = CostCentre::factory()->for($f['school'])->create();

    $transferred = app(TransferAssetAction::class)->execute($asset->id, $newCostCentre->id, $f['user']->id, 'Relocated to library.');

    expect($transferred->cost_centre_id)->toBe($newCostCentre->id);

    $movement = $transferred->movements()->first();
    expect(fn () => $movement->update(['reason' => 'tampered']))->toThrow(InvalidStateTransitionException::class);
    expect(fn () => $movement->delete())->toThrow(InvalidStateTransitionException::class);
});

it('flags an asset not found in verification and only lets a different user write it off (BR-FIN-10-012/AC-FIN-10-005)', function (): void {
    Event::fake([AssetNotFound::class]);
    $f = fin10Fixture();
    $asset = app(CapitalizeAssetAction::class)->execute(new CapitalizeAssetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        categoryId: $f['category']->id, name: 'Missing Projector', acquisitionDate: now(),
        acquisitionCostMinor: 300000, currency: 'USD', acquisitionSource: 'purchase',
        costCentreId: $f['costCentre']->id, contraAccountId: $f['contra']->id, performedByUserId: $f['user']->id,
    ));

    $count = app(CreateVerificationRoundAction::class)->execute($f['school']->id, 'ROUND-2026-Q3');
    expect($count)->toBeGreaterThanOrEqual(1);

    $verification = AssetVerification::where('asset_id', $asset->id)->where('verification_round', 'ROUND-2026-Q3')->first();

    $scanned = app(RecordAssetVerificationScanAction::class)->execute($verification->id, false, null, null, 'manual', $f['user']->id);
    expect($scanned->status)->toBe('not_found');
    Event::assertDispatched(AssetNotFound::class);

    expect(fn () => app(WriteOffNotFoundAssetAction::class)->execute($verification->id, $f['user']->id, 'Confirmed missing after search.'))
        ->toThrow(ValidationException::class);

    $writtenOff = app(WriteOffNotFoundAssetAction::class)->execute($verification->id, $f['user2']->id, 'Confirmed missing after search.');
    expect($writtenOff->status)->toBe('written_off');
});

it('records a discrepancy, not a failure, when an asset is found in an unexpected location (BR-FIN-10-011)', function (): void {
    $f = fin10Fixture();
    $asset = app(CapitalizeAssetAction::class)->execute(new CapitalizeAssetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        categoryId: $f['category']->id, name: 'Roaming Laptop', acquisitionDate: now(),
        acquisitionCostMinor: 400000, currency: 'USD', acquisitionSource: 'purchase',
        costCentreId: $f['costCentre']->id, contraAccountId: $f['contra']->id, performedByUserId: $f['user']->id,
    ));
    $asset->update(['location' => 'Staff Room A']);

    app(CreateVerificationRoundAction::class)->execute($f['school']->id, 'ROUND-LOC-1');
    $verification = AssetVerification::where('asset_id', $asset->id)->where('verification_round', 'ROUND-LOC-1')->first();

    $scanned = app(RecordAssetVerificationScanAction::class)->execute($verification->id, true, 'Library', null, 'barcode', $f['user']->id);

    expect($scanned->status)->toBe('discrepancy')
        ->and($scanned->found)->toBeTrue();
});

it('computes a gain on disposal against net book value and posts it (AC-FIN-10-006)', function (): void {
    $f = fin10Fixture();
    $asset = app(CapitalizeAssetAction::class)->execute(new CapitalizeAssetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        categoryId: $f['category']->id, name: 'School Vehicle', acquisitionDate: now(),
        acquisitionCostMinor: 400000, currency: 'USD', acquisitionSource: 'purchase',
        costCentreId: $f['costCentre']->id, contraAccountId: $f['contra']->id, performedByUserId: $f['user']->id,
        residualValueMinor: 0, usefulLifeYears: 1,
    ));
    // No depreciation run yet, so NBV is still the full 4,000 acquisition cost.
    $cashAccount = Account::factory()->for($f['school'])->create();

    expect(fn () => app(DisposeAssetAction::class)->execute(new DisposeAssetData(
        assetId: $asset->id, academicYearId: $f['year']->id, termId: $f['term']->id, disposalDate: now(),
        disposalMethod: 'sale', proceedsMinor: 550000, reason: 'Sold to staff member.', performedByUserId: $f['user']->id,
        proceedsAccountId: $cashAccount->id,
    )))->toThrow(DisposalRequiresApprovalException::class);

    $disposal = app(DisposeAssetAction::class)->execute(new DisposeAssetData(
        assetId: $asset->id, academicYearId: $f['year']->id, termId: $f['term']->id, disposalDate: now(),
        disposalMethod: 'sale', proceedsMinor: 550000, reason: 'Sold to staff member.', performedByUserId: $f['user']->id,
        proceedsAccountId: $cashAccount->id, approvedByUserId: $f['user2']->id,
    ));

    expect($disposal->gain_loss_minor)->toBe(150000)
        ->and($asset->fresh()->status)->toBe('disposed');
});

it('raises an under-insurance warning when sum insured falls below covered net book value (BR-FIN-10-016/AC-FIN-10-007)', function (): void {
    Event::fake([UnderInsuranceDetected::class]);
    $f = fin10Fixture();
    app(CapitalizeAssetAction::class)->execute(new CapitalizeAssetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        categoryId: $f['category']->id, name: 'Server Rack', acquisitionDate: now(),
        acquisitionCostMinor: 1000000, currency: 'USD', acquisitionSource: 'purchase',
        costCentreId: $f['costCentre']->id, contraAccountId: $f['contra']->id, performedByUserId: $f['user']->id,
    ));

    app(RecordInsurancePolicyAction::class)->execute(new RecordInsurancePolicyData(
        schoolId: $f['school']->id, policyNumber: 'POL-1', insurer: 'Old Mutual', policyType: 'all_risk',
        sumInsuredMinor: 600000, currency: 'USD', premiumMinor: 20000, startsOn: now(), expiresOn: now()->addYear(),
        categoryId: $f['category']->id,
    ));

    $underInsured = app(CheckUnderInsuranceAction::class)->execute($f['school']->id);

    expect($underInsured)->toHaveCount(1);
    Event::assertDispatched(UnderInsuranceDetected::class);
});

it('alerts when the register total diverges from the ledger asset account (BR-FIN-10-019/AC-FIN-10-008)', function (): void {
    Event::fake([RegisterLedgerDivergence::class]);
    $f = fin10Fixture();
    $asset = app(CapitalizeAssetAction::class)->execute(new CapitalizeAssetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        categoryId: $f['category']->id, name: 'Reconciled Asset', acquisitionDate: now(),
        acquisitionCostMinor: 700000, currency: 'USD', acquisitionSource: 'purchase',
        costCentreId: $f['costCentre']->id, contraAccountId: $f['contra']->id, performedByUserId: $f['user']->id,
    ));

    app(RebuildAccountBalancesAction::class)->execute(new RebuildAccountBalancesData(schoolId: $f['school']->id));

    $clean = app(ReconcileAssetRegisterAction::class)->execute($f['school']->id);
    expect($clean)->toHaveCount(0);

    // Simulate the register drifting from the ledger (e.g. a manual
    // correction to the register without a matching journal).
    $asset->update(['acquisition_cost_minor' => 750000]);

    $diverged = app(ReconcileAssetRegisterAction::class)->execute($f['school']->id);
    expect($diverged)->toHaveCount(1);
    Event::assertDispatched(RegisterLedgerDivergence::class);
});
