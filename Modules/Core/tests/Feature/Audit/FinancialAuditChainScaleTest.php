<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Support\Audit\FinancialAuditChainCheck;
use Modules\Core\Domain\Support\Sessions\CanonicalPayloadHasher;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

/**
 * Book A Acceptance Gate, Integrity gate: "Financial audit hash chain
 * verification passes over 10,000 seeded events." Bulk-inserted
 * directly (bypassing RecordFinancialAuditEntryAction's own per-row
 * transaction/lock, already covered by FinancialAuditLogTest) since
 * this test's own purpose is the chain-walking check's correctness and
 * performance at scale, not re-proving how a single row gets recorded.
 */
function buildFinancialAuditChain(int $schoolId, int $causerId, int $academicYearId, int $count): void
{
    $rows = [];
    $previousHash = null;

    for ($sequence = 1; $sequence <= $count; $sequence++) {
        $payload = ['note' => "event {$sequence}", 'amount' => $sequence * 100];
        $payloadHash = CanonicalPayloadHasher::hash($payload);

        $rows[] = [
            'school_id' => $schoolId,
            'sequence' => $sequence,
            'event_type' => 'receipt.posted',
            'subject_type' => 'test',
            'subject_id' => $sequence,
            'amount_minor' => $sequence * 100,
            'amount_currency' => 'USD',
            'payload' => json_encode($payload),
            'payload_hash' => $payloadHash,
            'previous_hash' => $previousHash,
            'causer_id' => $causerId,
            'impersonator_id' => null,
            'academic_year_id' => $academicYearId,
            'term_id' => null,
            'ip_address' => null,
            'occurred_at' => now(),
        ];

        $previousHash = $payloadHash;
    }

    foreach (array_chunk($rows, 1000) as $chunk) {
        DB::table('financial_audit_log')->insert($chunk);
    }
}

it('verifies a 10,000-event financial audit chain (Book A Acceptance Gate, Integrity)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $year = AcademicYear::factory()->create(['school_id' => $school->id]);

    buildFinancialAuditChain($school->id, $user->id, $year->id, 10000);

    $result = (new FinancialAuditChainCheck)->run($school->id);

    expect($result->status)->toBe('passed')
        ->and($result->recordsChecked)->toBe(10000)
        ->and($result->failuresFound)->toBe(0);
});

it('catches a single tampered payload inside a 10,000-event chain', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $year = AcademicYear::factory()->create(['school_id' => $school->id]);

    buildFinancialAuditChain($school->id, $user->id, $year->id, 10000);

    DB::table('financial_audit_log')
        ->where('school_id', $school->id)
        ->where('sequence', 5000)
        ->update(['payload' => json_encode(['note' => 'tampered', 'amount' => 999999])]);

    $result = (new FinancialAuditChainCheck)->run($school->id);

    expect($result->status)->toBe('failed')
        ->and($result->failuresFound)->toBe(1)
        ->and($result->failureDetails[0]['sequence'])->toBe(5000)
        ->and($result->failureDetails[0]['reason'])->toContain('payload_hash mismatch');
});
