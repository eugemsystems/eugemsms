<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Actions\Audit\RecordFinancialAuditEntryAction;
use Modules\Core\Domain\Actions\Audit\RunIntegrityChecksAction;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Documents\VoidAllocatedNumberAction;
use Modules\Core\Domain\DataObjects\Audit\RecordFinancialAuditEntryData;
use Modules\Core\Domain\DataObjects\Audit\RunIntegrityChecksData;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Documents\VoidAllocatedNumberData;
use Modules\Core\Domain\Support\Audit\FinancialAuditChainCheck;
use Modules\Core\Domain\Support\Audit\NumberingGapCheck;
use Modules\Core\Domain\Support\Audit\SnapshotChainCheck;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\AllocatedNumber;
use Modules\Core\Models\School;

it('passes the financial audit chain check for an untouched chain', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $user = User::factory()->create();

    foreach (range(1, 3) as $i) {
        app(RecordFinancialAuditEntryAction::class)->execute(new RecordFinancialAuditEntryData(
            schoolId: $school->id,
            eventType: 'receipt_issued',
            subjectType: 'receipt',
            subjectId: $i,
            causerId: $user->id,
            academicYearId: $year->id,
            payload: ['n' => $i],
            amount: Money::of(100, Currency::USD),
        ));
    }

    $result = (new FinancialAuditChainCheck)->run($school->id);

    expect($result->passed())->toBeTrue()
        ->and($result->recordsChecked)->toBe(3);
});

it('detects a tampered financial audit log payload (BR-CORE-08-008/AC-CORE-08-002)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $user = User::factory()->create();

    $entry = app(RecordFinancialAuditEntryAction::class)->execute(new RecordFinancialAuditEntryData(
        schoolId: $school->id,
        eventType: 'receipt_issued',
        subjectType: 'receipt',
        subjectId: 1,
        causerId: $user->id,
        academicYearId: $year->id,
        payload: ['amount' => 100],
    ));

    // Simulate direct database tampering — bypassing the model's own
    // append-only guard, exactly as a rogue DB-level actor would.
    DB::table('financial_audit_log')->where('id', $entry->id)->update(['payload' => json_encode(['amount' => 999999])]);

    $result = (new FinancialAuditChainCheck)->run($school->id);

    expect($result->passed())->toBeFalse()
        ->and($result->failureDetails[0]['reason'])->toContain('payload_hash mismatch');
});

it('raises a critical security event when RunIntegrityChecksAction finds a broken chain', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $user = User::factory()->create();

    $entry = app(RecordFinancialAuditEntryAction::class)->execute(new RecordFinancialAuditEntryData(
        schoolId: $school->id,
        eventType: 'receipt_issued',
        subjectType: 'receipt',
        subjectId: 1,
        causerId: $user->id,
        academicYearId: $year->id,
        payload: ['amount' => 100],
    ));

    DB::table('financial_audit_log')->where('id', $entry->id)->update(['payload_hash' => str_repeat('0', 64)]);

    app(RunIntegrityChecksAction::class)->execute(new RunIntegrityChecksData(schoolId: $school->id, checkTypes: ['audit_chain']));

    $this->assertDatabaseHas('security_events', ['event_type' => 'financial_audit_chain_broken', 'severity' => 'critical']);
    $this->assertDatabaseHas('integrity_check_runs', ['check_type' => 'audit_chain', 'status' => 'failed']);
});

it('passes the snapshot chain check by reusing CORE-03\'s canonical hasher', function (): void {
    $result = (new SnapshotChainCheck)->run(null);

    expect($result->passed())->toBeTrue();
});

it('flags a voided number with no reason on the numbering gap scan', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData($school->id, 'receipt', '{SEQ:6}'));
    $number = app(AllocateNumberAction::class)->execute(new AllocateNumberData($school->id, 'receipt', $user->id));
    app(VoidAllocatedNumberAction::class)->execute(new VoidAllocatedNumberData($number->id, 'Legitimate reason.', $user->id));

    expect((new NumberingGapCheck)->run($school->id)->passed())->toBeTrue();

    AllocatedNumber::withoutGlobalScopes()->whereKey($number->id)->update(['void_reason' => null]);

    $result = (new NumberingGapCheck)->run($school->id);
    expect($result->passed())->toBeFalse()
        ->and($result->failureDetails[0]['reason'])->toBe('voided with no reason recorded');
});
