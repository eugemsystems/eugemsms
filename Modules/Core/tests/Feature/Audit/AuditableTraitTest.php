<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\School;
use Modules\Core\Tests\Fixtures\TestAuditableHouse;

it('logs a create event with the new attributes (BR-CORE-08-001)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    Auth::login($user);

    $house = TestAuditableHouse::create(['school_id' => $school->id, 'code' => 'RED', 'name' => 'Red House']);

    $entry = ActivityLogEntry::where('subject_id', $house->id)->where('event', 'created')->sole();

    expect($entry->log_name)->toBe('test_auditable_house')
        ->and($entry->causer_id)->toBe($user->id)
        ->and($entry->properties['attributes']['name'])->toBe('Red House');
});

it('logs an update with only the changed fields, excluding auditExcluded ones (BR-CORE-08-002)', function (): void {
    $school = School::factory()->create();
    $house = TestAuditableHouse::create(['school_id' => $school->id, 'code' => 'RED', 'name' => 'Red House', 'colour' => '#ff0000']);

    $house->update(['name' => 'Crimson House', 'colour' => '#dc143c']);

    $entry = ActivityLogEntry::where('subject_id', $house->id)->where('event', 'updated')->sole();

    expect($entry->properties['attributes'])->toHaveKey('name')
        ->and($entry->properties['attributes'])->not->toHaveKey('colour')
        ->and($entry->properties['old']['name'])->toBe('Red House');
});

it('logs a delete event', function (): void {
    $school = School::factory()->create();
    $house = TestAuditableHouse::create(['school_id' => $school->id, 'code' => 'RED', 'name' => 'Red House']);
    $houseId = $house->id;

    $house->delete();

    $entry = ActivityLogEntry::where('subject_id', $houseId)->where('event', 'deleted')->sole();
    expect($entry->description)->toContain('deleted');
});

it('does not log an update when nothing meaningful changed', function (): void {
    $school = School::factory()->create();
    $house = TestAuditableHouse::create(['school_id' => $school->id, 'code' => 'RED', 'name' => 'Red House']);

    $house->touch();

    expect(ActivityLogEntry::where('subject_id', $house->id)->where('event', 'updated')->count())->toBe(0);
});
