<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Audit\RecordDataAccessAction;
use Modules\Core\Domain\Actions\Audit\RecordSecurityEventAction;
use Modules\Core\Domain\Actions\Audit\ReviewSecurityEventAction;
use Modules\Core\Domain\DataObjects\Audit\RecordDataAccessData;
use Modules\Core\Domain\DataObjects\Audit\RecordSecurityEventData;
use Modules\Core\Domain\DataObjects\Audit\ReviewSecurityEventData;
use Modules\Core\Models\School;
use Modules\Core\Models\SecurityEvent;

it('records a security event', function (): void {
    $school = School::factory()->create();

    $event = app(RecordSecurityEventAction::class)->execute(new RecordSecurityEventData(
        eventType: 'repeated_login_failure',
        severity: 'warning',
        description: 'Five failed logins for tendai@example.com',
        schoolId: $school->id,
    ));

    expect($event->is_reviewed)->toBeFalse()
        ->and($event->isCritical())->toBeFalse();
});

it('marks a security event reviewed with notes', function (): void {
    $reviewer = User::factory()->create();
    $event = SecurityEvent::factory()->create();

    $reviewed = app(ReviewSecurityEventAction::class)->execute(new ReviewSecurityEventData($event->id, $reviewer->id, 'False alarm — the user forgot their new password.'));

    expect($reviewed->is_reviewed)->toBeTrue()
        ->and($reviewed->reviewed_by)->toBe($reviewer->id);
});

it('records a data access log entry for a sensitive read (BR-CORE-08-009/AC-CORE-08-004)', function (): void {
    $school = School::factory()->create();
    $nurse = User::factory()->create();

    $entry = app(RecordDataAccessAction::class)->execute(new RecordDataAccessData(
        schoolId: $school->id,
        userId: $nurse->id,
        accessType: 'view',
        resourceType: 'medical_record',
        resourceId: 42,
    ));

    expect($entry->resource_type)->toBe('medical_record');
});

it('raises a security event when a bulk export exceeds the threshold (BR-CORE-08-010/AC-CORE-08-005)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    app(RecordDataAccessAction::class)->execute(new RecordDataAccessData(
        schoolId: $school->id,
        userId: $user->id,
        accessType: 'export',
        resourceType: 'learner_bulk',
        recordCount: 1500,
    ));

    $this->assertDatabaseHas('security_events', ['event_type' => 'bulk_export', 'school_id' => $school->id]);
});

it('does not raise a security event for an export under the threshold', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    app(RecordDataAccessAction::class)->execute(new RecordDataAccessData(
        schoolId: $school->id,
        userId: $user->id,
        accessType: 'export',
        resourceType: 'learner_bulk',
        recordCount: 10,
    ));

    $this->assertDatabaseMissing('security_events', ['event_type' => 'bulk_export']);
});
