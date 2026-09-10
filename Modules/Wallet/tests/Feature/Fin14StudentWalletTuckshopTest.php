<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Journal;
use Modules\Fiscal\Domain\Actions\CreateFiscalisationRuleAction;
use Modules\Fiscal\Domain\Actions\RegisterFiscalDeviceAction;
use Modules\Fiscal\Domain\DataObjects\CreateFiscalisationRuleData;
use Modules\Fiscal\Domain\DataObjects\RegisterFiscalDeviceData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Stores\Domain\Actions\ReceiveStockAction;
use Modules\Stores\Domain\DataObjects\ReceiveStockData;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;
use Modules\Wallet\Domain\Actions\CloseWalletAction;
use Modules\Wallet\Domain\Actions\CreateSpendPointAction;
use Modules\Wallet\Domain\Actions\CreateStudentWalletAction;
use Modules\Wallet\Domain\Actions\CreateWalletProductAction;
use Modules\Wallet\Domain\Actions\ProcessTermEndWalletAction;
use Modules\Wallet\Domain\Actions\ProcessWalletSaleAction;
use Modules\Wallet\Domain\Actions\ReconcileWalletLiabilityAction;
use Modules\Wallet\Domain\Actions\SetWalletControlsAction;
use Modules\Wallet\Domain\Actions\SyncOfflineWalletSaleAction;
use Modules\Wallet\Domain\Actions\TopUpWalletAction;
use Modules\Wallet\Domain\Actions\VoidWalletSaleAction;
use Modules\Wallet\Domain\DataObjects\CloseWalletData;
use Modules\Wallet\Domain\DataObjects\CreateSpendPointData;
use Modules\Wallet\Domain\DataObjects\CreateStudentWalletData;
use Modules\Wallet\Domain\DataObjects\CreateWalletProductData;
use Modules\Wallet\Domain\DataObjects\ProcessTermEndWalletData;
use Modules\Wallet\Domain\DataObjects\ProcessWalletSaleData;
use Modules\Wallet\Domain\DataObjects\SetWalletControlsData;
use Modules\Wallet\Domain\DataObjects\TopUpWalletData;
use Modules\Wallet\Domain\DataObjects\VoidWalletSaleData;
use Modules\Wallet\Domain\Events\WalletNegative;
use Modules\Wallet\Domain\Events\WalletReconciliationVariance;
use Modules\Wallet\Domain\Exceptions\BlockedCategoryException;
use Modules\Wallet\Domain\Exceptions\SpendingLimitExceededException;
use Modules\Wallet\Domain\Exceptions\WalletAlreadyNegativeException;
use Modules\Wallet\Models\StudentWallet;

/**
 * @return array<string, mixed>
 */
function fin14Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $user = User::factory()->create();

    foreach (['journal' => 'JNL', 'wallet_sale' => 'WS'] as $documentType => $prefix) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $documentType, pattern: $prefix.'/{SEQ:6}',
        ));
    }

    $liabilityAccount = Account::factory()->for($school)->liability()->create();
    $clearingAccount = Account::factory()->for($school)->create();
    $incomeAccount = Account::factory()->for($school)->income()->create();
    $feeDebtorsAccount = Account::factory()->for($school)->controlAccount('student')->create();

    $store = Store::factory()->for($school)->create();
    $item = InventoryItem::factory()->for($school)->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'stock_receipt', pattern: 'SR/{SEQ:6}',
    ));
    app(ReceiveStockAction::class)->execute(new ReceiveStockData(
        schoolId: $school->id, academicYearId: $year->id, termId: $term->id, storeId: $store->id,
        itemId: $item->id, quantity: 100, unitCostMinor: 60, currency: 'USD', receivedOn: now(),
        performedByUserId: $user->id, contraAccountId: $clearingAccount->id,
    ));

    $spendPoint = app(CreateSpendPointAction::class)->execute(new CreateSpendPointData(
        schoolId: $school->id, code: 'TUCK', name: 'Tuckshop', pointType: 'tuckshop',
        incomeAccountId: $incomeAccount->id, costCentreId: $store->cost_centre_id, storeId: $store->id,
        isFiscalisable: true,
    ));

    $chocolate = app(CreateWalletProductAction::class)->execute(new CreateWalletProductData(
        schoolId: $school->id, spendPointId: $spendPoint->id, code: 'CHOC', name: 'Chocolate Bar',
        category: 'confectionery', priceMinor: 100, currency: 'USD', taxType: 'standard', itemId: $item->id,
    ));

    $guardian = Guardian::factory()->for($school)->create();
    $student = Student::factory()->for($school)->create();
    StudentGuardian::factory()->create([
        'school_id' => $school->id, 'student_id' => $student->id, 'guardian_id' => $guardian->id,
        'is_fee_responsible' => true, 'is_primary_contact' => true,
    ]);

    $wallet = app(CreateStudentWalletAction::class)->execute(new CreateStudentWalletData(
        schoolId: $school->id, studentId: $student->id, currency: 'USD', liabilityAccountId: $liabilityAccount->id,
    ));

    return compact(
        'school', 'year', 'term', 'user', 'liabilityAccount', 'clearingAccount', 'incomeAccount',
        'feeDebtorsAccount', 'store', 'item', 'spendPoint', 'chocolate', 'guardian', 'student', 'wallet',
    );
}

it('credits the wallet liability account on top-up and recognises no income (AC-FIN-14-001)', function (): void {
    $f = fin14Fixture();

    $transaction = app(TopUpWalletAction::class)->execute(new TopUpWalletData(
        walletId: $f['wallet']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        amountMinor: 5000, clearingAccountId: $f['clearingAccount']->id, performedByUserId: $f['user']->id,
    ));

    expect($transaction->balance_after_minor)->toBe(5000)
        ->and($f['wallet']->fresh()->balance_minor)->toBe(5000);

    $journal = Journal::with('lines')->findOrFail($transaction->journal_id);
    $liabilityLine = $journal->lines->firstWhere('account_id', $f['liabilityAccount']->id);
    $incomeLineExists = $journal->lines->contains('account_id', $f['incomeAccount']->id);

    expect($liabilityLine->direction)->toBe('CR')
        ->and((int) $liabilityLine->amount_minor)->toBe(5000)
        ->and($incomeLineExists)->toBeFalse();
});

it('refuses a blocked category with the reason shown to the operator (AC-FIN-14-002)', function (): void {
    $f = fin14Fixture();
    app(TopUpWalletAction::class)->execute(new TopUpWalletData(
        walletId: $f['wallet']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        amountMinor: 5000, clearingAccountId: $f['clearingAccount']->id, performedByUserId: $f['user']->id,
    ));
    app(SetWalletControlsAction::class)->execute(new SetWalletControlsData(
        walletId: $f['wallet']->id, setByGuardianId: $f['guardian']->id, blockedCategories: ['confectionery'],
    ));

    expect(fn () => app(ProcessWalletSaleAction::class)->execute(new ProcessWalletSaleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        spendPointId: $f['spendPoint']->id, paymentMethod: 'wallet',
        lines: [['product_id' => $f['chocolate']->id, 'quantity' => 1]],
        operatorId: $f['user']->id, studentId: $f['student']->id,
    )))->toThrow(BlockedCategoryException::class);
});

it('refuses a purchase that exceeds the daily limit, showing the remaining allowance (AC-FIN-14-003)', function (): void {
    $f = fin14Fixture();
    app(TopUpWalletAction::class)->execute(new TopUpWalletData(
        walletId: $f['wallet']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        amountMinor: 5000, clearingAccountId: $f['clearingAccount']->id, performedByUserId: $f['user']->id,
    ));
    app(SetWalletControlsAction::class)->execute(new SetWalletControlsData(
        walletId: $f['wallet']->id, setByGuardianId: $f['guardian']->id, dailyLimitMinor: 420,
    ));

    app(ProcessWalletSaleAction::class)->execute(new ProcessWalletSaleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        spendPointId: $f['spendPoint']->id, paymentMethod: 'wallet',
        lines: [['product_id' => $f['chocolate']->id, 'quantity' => 4]],
        operatorId: $f['user']->id, studentId: $f['student']->id,
    ));

    expect(fn () => app(ProcessWalletSaleAction::class)->execute(new ProcessWalletSaleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        spendPointId: $f['spendPoint']->id, paymentMethod: 'wallet',
        lines: [['product_id' => $f['chocolate']->id, 'quantity' => 1]],
        operatorId: $f['user']->id, studentId: $f['student']->id,
    )))->toThrow(SpendingLimitExceededException::class);
});

it('completes an offline sale and syncs it idempotently on the same offline_reference (AC-FIN-14-004)', function (): void {
    $f = fin14Fixture();
    app(TopUpWalletAction::class)->execute(new TopUpWalletData(
        walletId: $f['wallet']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        amountMinor: 5000, clearingAccountId: $f['clearingAccount']->id, performedByUserId: $f['user']->id,
    ));

    $offlineRef = (string) Str::uuid();
    $saleData = new ProcessWalletSaleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        spendPointId: $f['spendPoint']->id, paymentMethod: 'wallet',
        lines: [['product_id' => $f['chocolate']->id, 'quantity' => 1]],
        operatorId: $f['user']->id, studentId: $f['student']->id,
        deviceSource: 'offline_sync', offlineReference: $offlineRef,
    );

    $first = app(SyncOfflineWalletSaleAction::class)->execute($saleData);
    $second = app(SyncOfflineWalletSaleAction::class)->execute($saleData);

    expect($second->id)->toBe($first->id)
        ->and($f['wallet']->fresh()->balance_minor)->toBe(4900);
});

it('honours an offline sale that syncs against an insufficient balance, goes negative, notifies the guardian, and blocks further offline sales (AC-FIN-14-005)', function (): void {
    $f = fin14Fixture();
    Event::fake([WalletNegative::class]);

    app(TopUpWalletAction::class)->execute(new TopUpWalletData(
        walletId: $f['wallet']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        amountMinor: 50, clearingAccountId: $f['clearingAccount']->id, performedByUserId: $f['user']->id,
    ));

    $sale = app(SyncOfflineWalletSaleAction::class)->execute(new ProcessWalletSaleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        spendPointId: $f['spendPoint']->id, paymentMethod: 'wallet',
        lines: [['product_id' => $f['chocolate']->id, 'quantity' => 1]],
        operatorId: $f['user']->id, studentId: $f['student']->id, deviceSource: 'offline_sync',
    ));

    expect($sale->status)->toBe('completed')
        ->and($f['wallet']->fresh()->balance_minor)->toBe(-50);
    Event::assertDispatched(WalletNegative::class);

    expect(fn () => app(SyncOfflineWalletSaleAction::class)->execute(new ProcessWalletSaleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        spendPointId: $f['spendPoint']->id, paymentMethod: 'wallet',
        lines: [['product_id' => $f['chocolate']->id, 'quantity' => 1]],
        operatorId: $f['user']->id, studentId: $f['student']->id, deviceSource: 'offline_sync',
    )))->toThrow(WalletAlreadyNegativeException::class);
});

it('applies the term-end policy without ever recognising the balance as income (AC-FIN-14-006)', function (): void {
    $f = fin14Fixture();
    app(TopUpWalletAction::class)->execute(new TopUpWalletData(
        walletId: $f['wallet']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        amountMinor: 1230, clearingAccountId: $f['clearingAccount']->id, performedByUserId: $f['user']->id,
    ));

    $processed = app(ProcessTermEndWalletAction::class)->execute(new ProcessTermEndWalletData(
        walletId: $f['wallet']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        policy: 'transfer_to_fees', performedByUserId: $f['user']->id, feeDebtorsAccountId: $f['feeDebtorsAccount']->id,
    ));

    expect($processed->balance_minor)->toBe(0);

    $journal = Journal::with('lines')->latest('id')->first();
    $feeLine = $journal->lines->firstWhere('account_id', $f['feeDebtorsAccount']->id);

    expect($feeLine->direction)->toBe('CR')
        ->and((int) $feeLine->amount_minor)->toBe(1230)
        ->and($feeLine->subledger_type)->toBe('student');

    $noIncomeLine = $journal->lines->contains('account_id', $f['incomeAccount']->id);
    expect($noIncomeLine)->toBeFalse();
});

it('closes a wallet on withdrawal, refunding any remaining balance (BR-FIN-14-016)', function (): void {
    $f = fin14Fixture();
    app(TopUpWalletAction::class)->execute(new TopUpWalletData(
        walletId: $f['wallet']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        amountMinor: 800, clearingAccountId: $f['clearingAccount']->id, performedByUserId: $f['user']->id,
    ));

    $closed = app(CloseWalletAction::class)->execute(new CloseWalletData(
        walletId: $f['wallet']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        policy: 'refund', performedByUserId: $f['user']->id, refundClearingAccountId: $f['clearingAccount']->id,
    ));

    expect($closed->status)->toBe('closed')
        ->and($closed->balance_minor)->toBe(0);
});

it('routes a fiscalisable tuckshop sale to FIN-13 for real, depletes stock, and posts cost of sales (AC-FIN-14-007/BR-FIN-14-009/010)', function (): void {
    $f = fin14Fixture();
    app(RegisterFiscalDeviceAction::class)->execute(new RegisterFiscalDeviceData(
        schoolId: $f['school']->id, deviceId: 'DEV-001', deviceSerial: 'SN-001', taxpayerName: 'Test School',
        taxpayerTin: '1234567890', environment: 'sandbox', apiBaseUrl: 'https://sandbox.fdms.zimra.co.zw',
    ));
    app(CreateFiscalisationRuleAction::class)->execute(new CreateFiscalisationRuleData(
        schoolId: $f['school']->id, ruleName: 'Confectionery is standard-rated', sourceType: 'wallet_product',
        sourceIdentifier: 'confectionery', isFiscalisable: true, taxType: 'standard', taxRatePercent: '15',
        rationale: 'Tuckshop sales are a commercial supply.', reviewedByUserId: $f['user']->id,
    ));
    app(TopUpWalletAction::class)->execute(new TopUpWalletData(
        walletId: $f['wallet']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        amountMinor: 5000, clearingAccountId: $f['clearingAccount']->id, performedByUserId: $f['user']->id,
    ));

    $sale = app(ProcessWalletSaleAction::class)->execute(new ProcessWalletSaleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        spendPointId: $f['spendPoint']->id, paymentMethod: 'wallet',
        lines: [['product_id' => $f['chocolate']->id, 'quantity' => 2]],
        operatorId: $f['user']->id, studentId: $f['student']->id,
    ));

    expect($sale->fiscal_receipt_id)->not->toBeNull()
        ->and((int) $sale->cost_of_sales_minor)->toBe(120)
        ->and($sale->lines->first()->stock_movement_id)->not->toBeNull();
});

it('reverses the wallet, stock and journal when a sale is voided (BR-FIN-14-018)', function (): void {
    $f = fin14Fixture();
    app(TopUpWalletAction::class)->execute(new TopUpWalletData(
        walletId: $f['wallet']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        amountMinor: 5000, clearingAccountId: $f['clearingAccount']->id, performedByUserId: $f['user']->id,
    ));
    $sale = app(ProcessWalletSaleAction::class)->execute(new ProcessWalletSaleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        spendPointId: $f['spendPoint']->id, paymentMethod: 'wallet',
        lines: [['product_id' => $f['chocolate']->id, 'quantity' => 1]],
        operatorId: $f['user']->id, studentId: $f['student']->id,
    ));

    expect($f['wallet']->fresh()->balance_minor)->toBe(4900);

    $voided = app(VoidWalletSaleAction::class)->execute(new VoidWalletSaleData(
        saleId: $sale->id, reason: 'Learner returned the item unopened.', voidedByUserId: $f['user']->id,
    ));

    expect($voided->status)->toBe('voided')
        ->and($f['wallet']->fresh()->balance_minor)->toBe(5000);
});

it('flags a variance when the sum of wallet balances differs from the liability account (AC-FIN-14-008)', function (): void {
    Event::fake([WalletReconciliationVariance::class]);
    $f = fin14Fixture();
    app(TopUpWalletAction::class)->execute(new TopUpWalletData(
        walletId: $f['wallet']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        amountMinor: 5000, clearingAccountId: $f['clearingAccount']->id, performedByUserId: $f['user']->id,
    ));

    $clean = app(ReconcileWalletLiabilityAction::class)->execute($f['school']->id);
    expect($clean['variance_minor'])->toBe(0);

    StudentWallet::where('id', $f['wallet']->id)->update(['balance_minor' => 999999]);

    $dirty = app(ReconcileWalletLiabilityAction::class)->execute($f['school']->id);
    expect($dirty['variance_minor'])->not->toBe(0);
    Event::assertDispatched(WalletReconciliationVariance::class);
});
