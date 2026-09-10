<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Modules\Core\Domain\Actions\Schools\ArchiveSchoolAction;
use Modules\Core\Domain\Contracts\Schools\SchoolLifecycleGuard;
use Modules\Core\Domain\DataObjects\Schools\ArchiveSchoolData;
use Modules\Core\Domain\Events\Schools\SchoolArchived;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\School;

it('archives a school with no blocking conditions', function (): void {
    $school = School::factory()->create(['status' => 'active']);
    $admin = User::factory()->create();

    // Faked only now, not before the fixtures above: Event::fake() swaps
    // the shared dispatcher Eloquent's own model events (creating, etc.)
    // also flow through, so faking it first would have silently skipped
    // HasUlid's `creating` listener on the factory-created rows.
    Event::fake();

    app(ArchiveSchoolAction::class)->execute(new ArchiveSchoolData(
        schoolId: $school->id,
        actingUserId: $admin->id,
        reason: 'Closed permanently',
    ));

    expect($school->fresh()->status)->toBe('archived');
    Event::assertDispatched(SchoolArchived::class);
});

it('refuses to archive an already-archived school', function (): void {
    $school = School::factory()->create(['status' => 'archived']);
    $admin = User::factory()->create();

    app(ArchiveSchoolAction::class)->execute(new ArchiveSchoolData($school->id, $admin->id, 'again'));
})->throws(InvalidStateTransitionException::class);

it('refuses to archive while the lifecycle guard reports blockers (BR-CORE-02-005)', function (): void {
    $school = School::factory()->create(['status' => 'active']);
    $admin = User::factory()->create();

    $guard = Mockery::mock(SchoolLifecycleGuard::class);
    $guard->shouldReceive('archiveBlockers')->andReturn(['1 active learner']);
    app()->instance(SchoolLifecycleGuard::class, $guard);

    app(ArchiveSchoolAction::class)->execute(new ArchiveSchoolData($school->id, $admin->id, 'closing'));
})->throws(InvalidStateTransitionException::class);
