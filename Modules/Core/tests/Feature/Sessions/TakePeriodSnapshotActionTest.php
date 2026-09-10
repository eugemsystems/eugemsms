<?php

use Modules\Core\Domain\Actions\Sessions\TakePeriodSnapshotAction;
use Modules\Core\Domain\DataObjects\Sessions\SnapshotData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Sessions\CanonicalPayloadHasher;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\PeriodSnapshot;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

it('hashes the canonical payload (BR-CORE-03-015)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

    $snapshot = app(TakePeriodSnapshotAction::class)->execute(new SnapshotData(
        schoolId: $school->id,
        academicYearId: $year->id,
        termId: $term->id,
        snapshotType: 'pre_close',
    ));

    expect($snapshot->payload_hash)->toBe(CanonicalPayloadHasher::hash($snapshot->payload))
        ->and($snapshot->previous_hash)->toBeNull();
});

it('chains to the school\'s prior snapshot hash', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

    $first = app(TakePeriodSnapshotAction::class)->execute(new SnapshotData($school->id, $year->id, $term->id, 'pre_close'));
    $second = app(TakePeriodSnapshotAction::class)->execute(new SnapshotData($school->id, $year->id, $term->id, 'post_close'));

    expect($second->previous_hash)->toBe($first->payload_hash);
});

it('produces identical hashes for the same logical payload regardless of key order', function (): void {
    expect(CanonicalPayloadHasher::hash(['b' => 2, 'a' => 1]))
        ->toBe(CanonicalPayloadHasher::hash(['a' => 1, 'b' => 2]));
});

it('refuses to update a snapshot after the fact (BR-CORE-03-016)', function (): void {
    $snapshot = PeriodSnapshot::factory()->create();

    $snapshot->payload = ['tampered' => true];
    $snapshot->save();
})->throws(InvalidStateTransitionException::class);

it('refuses to delete a snapshot', function (): void {
    $snapshot = PeriodSnapshot::factory()->create();

    $snapshot->delete();
})->throws(InvalidStateTransitionException::class);
