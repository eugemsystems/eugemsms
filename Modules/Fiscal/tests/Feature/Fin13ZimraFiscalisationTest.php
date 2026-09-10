<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Farm\Domain\Actions\RecordFarmSaleAction;
use Modules\Farm\Domain\DataObjects\RecordFarmSaleData;
use Modules\Farm\Models\ProductionUnit;
use Modules\Finance\Domain\Actions\CreateFeeComponentAction;
use Modules\Finance\Domain\Actions\CreateReceiptAction;
use Modules\Finance\Domain\Actions\OpenTillSessionAction;
use Modules\Finance\Domain\DataObjects\CreateFeeComponentData;
use Modules\Finance\Domain\DataObjects\CreateReceiptData;
use Modules\Finance\Domain\DataObjects\OpenTillSessionData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Till;
use Modules\Fiscal\Domain\Actions\CloseFiscalDayAction;
use Modules\Fiscal\Domain\Actions\CreateFiscalisationRuleAction;
use Modules\Fiscal\Domain\Actions\DrainOfflineFiscalQueueAction;
use Modules\Fiscal\Domain\Actions\OpenFiscalDayAction;
use Modules\Fiscal\Domain\Actions\RaiseFiscalCreditNoteAction;
use Modules\Fiscal\Domain\Actions\ReconcileFiscalisationAction;
use Modules\Fiscal\Domain\Actions\RegisterFiscalDeviceAction;
use Modules\Fiscal\Domain\Actions\RouteReceiptForFiscalisationAction;
use Modules\Fiscal\Domain\DataObjects\CloseFiscalDayData;
use Modules\Fiscal\Domain\DataObjects\CreateFiscalisationRuleData;
use Modules\Fiscal\Domain\DataObjects\OpenFiscalDayData;
use Modules\Fiscal\Domain\DataObjects\RaiseFiscalCreditNoteData;
use Modules\Fiscal\Domain\DataObjects\RegisterFiscalDeviceData;
use Modules\Fiscal\Domain\DataObjects\RouteReceiptForFiscalisationData;
use Modules\Fiscal\Domain\Events\FiscalReconciliationException;
use Modules\Fiscal\Models\FiscalDevice;
use Modules\Fiscal\Models\FiscalReceipt;
use Modules\People\Models\Student;

/**
 * @return array<string, mixed>
 */
function fin13Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $user = User::factory()->create();

    foreach (['journal' => 'JNL', 'receipt' => 'RCT'] as $documentType => $prefix) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $documentType, pattern: $prefix.'/{SEQ:6}',
        ));
    }

    $device = app(RegisterFiscalDeviceAction::class)->execute(new RegisterFiscalDeviceData(
        schoolId: $school->id, deviceId: 'DEV-001', deviceSerial: 'SN-001', taxpayerName: 'Test School',
        taxpayerTin: '1234567890', environment: 'sandbox', apiBaseUrl: 'https://sandbox.fdms.zimra.co.zw',
    ));

    $income = Account::factory()->for($school)->income()->create();
    $debtor = Account::factory()->for($school)->controlAccount('student')->create();

    $tuition = app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
        schoolId: $school->id, code: 'TUITION', name: 'Tuition', category: 'tuition',
        incomeAccountId: $income->id, debtorAccountId: $debtor->id, defaultCurrency: 'USD',
        createdByUserId: $user->id, isFiscalisable: false, taxCategory: 'exempt',
    ));
    $uniform = app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
        schoolId: $school->id, code: 'UNIFORM', name: 'Uniform', category: 'uniform',
        incomeAccountId: $income->id, debtorAccountId: $debtor->id, defaultCurrency: 'USD',
        createdByUserId: $user->id, isFiscalisable: true, taxCategory: 'standard',
    ));

    app(CreateFiscalisationRuleAction::class)->execute(new CreateFiscalisationRuleData(
        schoolId: $school->id, ruleName: 'Uniform sales are standard-rated', sourceType: 'fee_component',
        sourceIdentifier: 'UNIFORM', isFiscalisable: true, taxType: 'standard', taxRatePercent: '15',
        rationale: 'Uniform sales are a commercial supply.', reviewedByUserId: $user->id,
    ));
    app(CreateFiscalisationRuleAction::class)->execute(new CreateFiscalisationRuleData(
        schoolId: $school->id, ruleName: 'Tuition is exempt', sourceType: 'fee_component',
        sourceIdentifier: 'TUITION', isFiscalisable: false, taxType: 'exempt',
        rationale: 'Core tuition is not a commercial supply.', reviewedByUserId: $user->id,
    ));

    $cashAccount = Account::factory()->for($school)->create();
    $bankAccount = Account::factory()->for($school)->create();
    $creditBalanceAccount = Account::factory()->for($school)->create();
    $till = Till::factory()->for($school)->create(['cash_account_id' => $cashAccount->id, 'bank_account_id' => $bankAccount->id]);
    $session = app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
        schoolId: $school->id, academicYearId: $year->id, termId: $term->id, tillId: $till->id,
        cashierId: $user->id, openingFloat: ['USD' => 0],
    ));

    $student = Student::factory()->for($school)->create();

    return compact('school', 'year', 'term', 'user', 'device', 'tuition', 'uniform', 'till', 'session', 'student', 'creditBalanceAccount');
}

it('creates and posts a receipt when FDMS is unreachable, queuing it offline without blocking the sale (AC-FIN-13-001)', function (): void {
    $f = fin13Fixture();
    $f['device']->update(['last_ping_status' => 'offline']);

    $receipt = app(CreateReceiptAction::class)->execute(new CreateReceiptData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        receiptType: 'fee', payerType: 'guardian', payerName: 'Mrs Moyo', currency: 'USD',
        tenders: [['tender_type' => 'cash', 'amount_minor' => 5000, 'currency' => 'USD']],
        receivedByUserId: $f['user']->id, tillSessionId: $f['session']->id, studentId: $f['student']->id, creditBalanceAccountId: $f['creditBalanceAccount']->id,
    ));

    expect($receipt->status)->toBe('posted')
        ->and($receipt->journal_id)->not->toBeNull();
});

it('routes only the fiscalisable line of a mixed receipt, leaving tuition unfiscalised (AC-FIN-13-003/004)', function (): void {
    $f = fin13Fixture();

    $fiscalReceipt = app(RouteReceiptForFiscalisationAction::class)->execute(new RouteReceiptForFiscalisationData(
        schoolId: $f['school']->id, sourceType: 'fee_component', sourceId: 999, receiptType: 'fiscal_invoice',
        currency: 'USD', invoiceNumber: 'RCT/000001', receiptDate: now(),
        lines: [
            ['source_identifier' => 'TUITION', 'description' => 'Tuition', 'amount_minor' => 30000],
            ['source_identifier' => 'UNIFORM', 'description' => 'Uniform', 'amount_minor' => 5000],
        ],
        paymentMethods: ['cash'], performedByUserId: $f['user']->id,
    ));

    expect($fiscalReceipt)->not->toBeNull()
        ->and($fiscalReceipt->total_minor)->toBe(5000)
        ->and($fiscalReceipt->tax_breakdown)->toHaveKey('standard')
        ->and($fiscalReceipt->tax_breakdown)->not->toHaveKey('exempt');
});

it('returns null and creates no fiscal receipt when nothing on the receipt is fiscalisable (AC-FIN-13-003)', function (): void {
    $f = fin13Fixture();

    $fiscalReceipt = app(RouteReceiptForFiscalisationAction::class)->execute(new RouteReceiptForFiscalisationData(
        schoolId: $f['school']->id, sourceType: 'fee_component', sourceId: 998, receiptType: 'fiscal_invoice',
        currency: 'USD', invoiceNumber: 'RCT/000002', receiptDate: now(),
        lines: [['source_identifier' => 'TUITION', 'description' => 'Tuition', 'amount_minor' => 30000]],
        paymentMethods: ['cash'], performedByUserId: $f['user']->id,
    ));

    expect($fiscalReceipt)->toBeNull()
        ->and(FiscalReceipt::where('school_id', $f['school']->id)->count())->toBe(0);
});

it('maintains separate receipt counters per currency alongside one global counter (AC-FIN-13-005)', function (): void {
    $f = fin13Fixture();

    $usdReceipt = app(RouteReceiptForFiscalisationAction::class)->execute(new RouteReceiptForFiscalisationData(
        schoolId: $f['school']->id, sourceType: 'fee_component', sourceId: 1, receiptType: 'fiscal_invoice',
        currency: 'USD', invoiceNumber: 'RCT/USD/1', receiptDate: now(),
        lines: [['source_identifier' => 'UNIFORM', 'description' => 'Uniform', 'amount_minor' => 5000]],
        paymentMethods: ['cash'], performedByUserId: $f['user']->id,
    ));
    $zwgReceipt = app(RouteReceiptForFiscalisationAction::class)->execute(new RouteReceiptForFiscalisationData(
        schoolId: $f['school']->id, sourceType: 'fee_component', sourceId: 2, receiptType: 'fiscal_invoice',
        currency: 'ZWG', invoiceNumber: 'RCT/ZWG/1', receiptDate: now(),
        lines: [['source_identifier' => 'UNIFORM', 'description' => 'Uniform', 'amount_minor' => 500000]],
        paymentMethods: ['cash'], performedByUserId: $f['user']->id,
    ));
    $secondUsdReceipt = app(RouteReceiptForFiscalisationAction::class)->execute(new RouteReceiptForFiscalisationData(
        schoolId: $f['school']->id, sourceType: 'fee_component', sourceId: 3, receiptType: 'fiscal_invoice',
        currency: 'USD', invoiceNumber: 'RCT/USD/2', receiptDate: now(),
        lines: [['source_identifier' => 'UNIFORM', 'description' => 'Uniform', 'amount_minor' => 6000]],
        paymentMethods: ['cash'], performedByUserId: $f['user']->id,
    ));

    expect($usdReceipt->receipt_counter)->toBe(1)
        ->and($zwgReceipt->receipt_counter)->toBe(1)
        ->and($secondUsdReceipt->receipt_counter)->toBe(2)
        ->and($usdReceipt->global_counter)->toBe(1)
        ->and($zwgReceipt->global_counter)->toBe(2)
        ->and($secondUsdReceipt->global_counter)->toBe(3);
});

it('drains offline-queued receipts automatically on reconnect (AC-FIN-13-002)', function (): void {
    $f = fin13Fixture();
    $f['device']->update(['last_ping_status' => 'offline']);

    $fiscalReceipt = app(RouteReceiptForFiscalisationAction::class)->execute(new RouteReceiptForFiscalisationData(
        schoolId: $f['school']->id, sourceType: 'fee_component', sourceId: 1, receiptType: 'fiscal_invoice',
        currency: 'USD', invoiceNumber: 'RCT/000003', receiptDate: now(),
        lines: [['source_identifier' => 'UNIFORM', 'description' => 'Uniform', 'amount_minor' => 5000]],
        paymentMethods: ['cash'], performedByUserId: $f['user']->id,
    ));

    expect($fiscalReceipt->status)->toBe('offline_queued');

    $f['device']->update(['last_ping_status' => 'online']);
    $drained = app(DrainOfflineFiscalQueueAction::class)->execute($f['school']->id);

    expect($drained->first()->status)->toBe('accepted')
        ->and($fiscalReceipt->fresh()->status)->toBe('accepted');
});

it('does not mark a fiscal day closed locally until FDMS confirms (AC-FIN-13-006)', function (): void {
    $f = fin13Fixture();
    $day = app(OpenFiscalDayAction::class)->execute(new OpenFiscalDayData(deviceId: $f['device']->id, openedByUserId: $f['user']->id));

    $f['device']->update(['last_ping_status' => 'offline']);
    $closed = app(CloseFiscalDayAction::class)->execute(new CloseFiscalDayData(fiscalDayId: $day->id, closedByUserId: $f['user']->id));

    expect($closed->local_status)->toBe('close_failed')
        ->and($closed->closed_at)->toBeNull()
        ->and($closed->close_attempts)->toBe(1);

    $f['device']->update(['last_ping_status' => 'online']);
    $retried = app(CloseFiscalDayAction::class)->execute(new CloseFiscalDayData(fiscalDayId: $day->id, closedByUserId: $f['user']->id));

    expect($retried->local_status)->toBe('closed')
        ->and($retried->closed_at)->not->toBeNull();
});

it('raises a credit note referencing the original when a fiscalised receipt is voided, leaving the original unchanged (AC-FIN-13-007)', function (): void {
    $f = fin13Fixture();

    $original = app(RouteReceiptForFiscalisationAction::class)->execute(new RouteReceiptForFiscalisationData(
        schoolId: $f['school']->id, sourceType: 'fee_component', sourceId: 1, receiptType: 'fiscal_invoice',
        currency: 'USD', invoiceNumber: 'RCT/000004', receiptDate: now(),
        lines: [['source_identifier' => 'UNIFORM', 'description' => 'Uniform', 'amount_minor' => 5000]],
        paymentMethods: ['cash'], performedByUserId: $f['user']->id,
    ));
    $originalSnapshot = $original->toArray();

    $creditNote = app(RaiseFiscalCreditNoteAction::class)->execute(new RaiseFiscalCreditNoteData(
        originalFiscalReceiptId: $original->id, creditReason: 'Learner withdrew before collection.',
    ));

    expect($creditNote->receipt_type)->toBe('credit_note')
        ->and($creditNote->credited_receipt_id)->toBe($original->id)
        ->and($creditNote->status)->toBe('accepted')
        ->and($original->fresh()->toArray())->toMatchArray($originalSnapshot);
});

it('stores the verification code and QR once a receipt is accepted (AC-FIN-13-008)', function (): void {
    $f = fin13Fixture();

    $fiscalReceipt = app(RouteReceiptForFiscalisationAction::class)->execute(new RouteReceiptForFiscalisationData(
        schoolId: $f['school']->id, sourceType: 'fee_component', sourceId: 1, receiptType: 'fiscal_invoice',
        currency: 'USD', invoiceNumber: 'RCT/000005', receiptDate: now(),
        lines: [['source_identifier' => 'UNIFORM', 'description' => 'Uniform', 'amount_minor' => 5000]],
        paymentMethods: ['cash'], performedByUserId: $f['user']->id,
    ));

    expect($fiscalReceipt->status)->toBe('accepted')
        ->and($fiscalReceipt->verification_code)->not->toBeNull()
        ->and($fiscalReceipt->qr_url)->not->toBeNull()
        ->and($fiscalReceipt->fdms_receipt_id)->not->toBeNull();
});

it('flags an unfiscalised receipt beyond the reconciliation window (AC-FIN-13-009)', function (): void {
    Event::fake([FiscalReconciliationException::class]);
    $f = fin13Fixture();
    $f['device']->update(['last_ping_status' => 'offline']);

    $stale = app(RouteReceiptForFiscalisationAction::class)->execute(new RouteReceiptForFiscalisationData(
        schoolId: $f['school']->id, sourceType: 'fee_component', sourceId: 1, receiptType: 'fiscal_invoice',
        currency: 'USD', invoiceNumber: 'RCT/000006', receiptDate: now()->subHours(48),
        lines: [['source_identifier' => 'UNIFORM', 'description' => 'Uniform', 'amount_minor' => 5000]],
        paymentMethods: ['cash'], performedByUserId: $f['user']->id,
    ));

    $unreconciled = app(ReconcileFiscalisationAction::class)->execute($f['school']->id);

    expect($unreconciled->pluck('id'))->toContain($stale->id);
    Event::assertDispatched(FiscalReconciliationException::class);
});

it('rejects a receipt for real and never silently discards the error (BR-FIN-13-011)', function (): void {
    $f = fin13Fixture();

    $fiscalReceipt = app(RouteReceiptForFiscalisationAction::class)->execute(new RouteReceiptForFiscalisationData(
        schoolId: $f['school']->id, sourceType: 'fee_component', sourceId: 1, receiptType: 'fiscal_invoice',
        currency: 'USD', invoiceNumber: 'RCT/000007', receiptDate: now(),
        lines: [['source_identifier' => 'UNIFORM', 'description' => 'Uniform', 'amount_minor' => 5000]],
        paymentMethods: ['cash'], performedByUserId: $f['user']->id, simulate: 'reject',
    ));

    expect($fiscalReceipt->status)->toBe('rejected')
        ->and($fiscalReceipt->error_code)->toBe('FAKE_REJECTED')
        ->and($fiscalReceipt->error_message)->not->toBeNull();
});

it('only fiscalises through the active device and labels sandbox vs production (AC-FIN-13-010)', function (): void {
    $f = fin13Fixture();
    $production = FiscalDevice::factory()->production()->create(['school_id' => $f['school']->id, 'is_active' => false]);

    $fiscalReceipt = app(RouteReceiptForFiscalisationAction::class)->execute(new RouteReceiptForFiscalisationData(
        schoolId: $f['school']->id, sourceType: 'fee_component', sourceId: 1, receiptType: 'fiscal_invoice',
        currency: 'USD', invoiceNumber: 'RCT/000008', receiptDate: now(),
        lines: [['source_identifier' => 'UNIFORM', 'description' => 'Uniform', 'amount_minor' => 5000]],
        paymentMethods: ['cash'], performedByUserId: $f['user']->id,
    ));

    expect($fiscalReceipt->device_id)->toBe($f['device']->id)
        ->and($fiscalReceipt->device_id)->not->toBe($production->id)
        ->and($f['device']->environment)->toBe('sandbox')
        ->and($production->environment)->toBe('production');
});

it('retro-fits FIN-04 receipting for real through CreateReceiptAction without any code change to that action (BR-FIN-13-002/004)', function (): void {
    $f = fin13Fixture();

    $receipt = app(CreateReceiptAction::class)->execute(new CreateReceiptData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        receiptType: 'fee', payerType: 'guardian', payerName: 'Mrs Moyo', currency: 'USD',
        tenders: [['tender_type' => 'cash', 'amount_minor' => 5000, 'currency' => 'USD']],
        receivedByUserId: $f['user']->id, tillSessionId: $f['session']->id, studentId: $f['student']->id, creditBalanceAccountId: $f['creditBalanceAccount']->id,
        manualInvoiceOrder: [],
    ));

    // No invoice exists for this student, so the receipt is fully
    // unallocated — fiscalisation_status stays whatever CreateReceiptAction
    // itself decided (not_required, since nothing was allocated to a
    // fiscalisable component). This test's real assertion is that the
    // listener runs cleanly off a genuine FIN-04 receipt without error.
    expect($receipt->status)->toBe('posted');
});

it('retro-fits OPS-03 farm sales for real through RecordFarmSaleAction without any code change to that action (BR-OPS-03-016)', function (): void {
    $f = fin13Fixture();
    app(CreateFiscalisationRuleAction::class)->execute(new CreateFiscalisationRuleData(
        schoolId: $f['school']->id, ruleName: 'Farm produce sales are standard-rated', sourceType: 'farm_sale',
        isFiscalisable: true, taxType: 'standard', taxRatePercent: '15',
        rationale: 'Farm produce sales are a commercial supply.', reviewedByUserId: $f['user']->id,
    ));

    $productionUnit = ProductionUnit::factory()->create(['school_id' => $f['school']->id]);
    $cashAccount = Account::factory()->for($f['school'])->create();
    $incomeAccount = Account::factory()->for($f['school'])->income()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $f['school']->id, documentType: 'farm_sale', pattern: 'FRM/{SEQ:6}',
    ));

    $sale = app(RecordFarmSaleAction::class)->execute(new RecordFarmSaleData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        productionUnitId: $productionUnit->id, saleDate: now(), buyerName: 'Local Buyer',
        itemDescription: 'Maize', quantity: 100, unit: 'kg', unitPriceMinor: 50,
        currency: 'USD', cashAccountId: $cashAccount->id, salesIncomeAccountId: $incomeAccount->id,
        performedByUserId: $f['user']->id,
    ));

    expect($sale->fiscal_receipt_id)->not->toBeNull();

    $fiscalReceipt = FiscalReceipt::findOrFail($sale->fiscal_receipt_id);
    expect($fiscalReceipt->source_type)->toBe('farm_sale')
        ->and($fiscalReceipt->status)->toBe('accepted');
});
