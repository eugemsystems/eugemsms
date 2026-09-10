<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Audit\RecordFinancialAuditEntryAction;
use Modules\Core\Domain\DataObjects\Audit\RecordFinancialAuditEntryData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\FinancialAuditLogEntry;
use Modules\Core\Models\School;

function recordFinancialEntry(School $school, AcademicYear $year, User $user, string $type = 'receipt_issued'): FinancialAuditLogEntry
{
    return app(RecordFinancialAuditEntryAction::class)->execute(new RecordFinancialAuditEntryData(
        schoolId: $school->id,
        eventType: $type,
        subjectType: 'receipt',
        subjectId: 1,
        causerId: $user->id,
        academicYearId: $year->id,
        payload: ['type' => $type, 'note' => 'test'],
        amount: Money::of(1000, Currency::USD),
    ));
}

it('allocates a gapless, monotonic sequence per school and chains the hash (BR-CORE-08-006/007/AC-CORE-08-001)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $user = User::factory()->create();

    $first = recordFinancialEntry($school, $year, $user);
    $second = recordFinancialEntry($school, $year, $user);

    expect($first->sequence)->toBe(1)
        ->and($second->sequence)->toBe(2)
        ->and($first->previous_hash)->toBeNull()
        ->and($second->previous_hash)->toBe($first->payload_hash);
});

it('keeps separate sequences per school', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $yearA = AcademicYear::factory()->for($schoolA)->create();
    $yearB = AcademicYear::factory()->for($schoolB)->create();
    $user = User::factory()->create();

    $entryA = recordFinancialEntry($schoolA, $yearA, $user);
    $entryB = recordFinancialEntry($schoolB, $yearB, $user);

    expect($entryA->sequence)->toBe(1)
        ->and($entryB->sequence)->toBe(1);
});

it('refuses to update or delete a financial audit log entry (BR-CORE-08-005)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $user = User::factory()->create();
    $entry = recordFinancialEntry($school, $year, $user);

    expect(fn () => $entry->update(['event_type' => 'tampered']))->toThrow(InvalidStateTransitionException::class);
    expect(fn () => $entry->delete())->toThrow(InvalidStateTransitionException::class);
});
