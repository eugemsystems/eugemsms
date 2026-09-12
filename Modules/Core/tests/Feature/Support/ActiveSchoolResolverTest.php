<?php

use App\Models\User;
use Modules\Core\Domain\Support\ActiveSchoolResolver;
use Modules\Core\Models\School;
use Modules\Core\Models\UserSessionPreference;

it('returns null for a guest', function (): void {
    expect(ActiveSchoolResolver::resolveId(null))->toBeNull();
});

it('falls back to the user\'s primary school when there is no session preference', function (): void {
    $user = User::factory()->create();
    $primary = School::factory()->create();
    $user->schools()->attach($primary, ['is_primary' => true, 'status' => 'active']);

    expect(ActiveSchoolResolver::resolveId($user))->toBe($primary->id);
});

it('prefers the most recently switched-to school over the primary one (BR-CORE-02-008)', function (): void {
    $user = User::factory()->create();
    $primary = School::factory()->create();
    $switchedTo = School::factory()->create();
    $user->schools()->attach($primary, ['is_primary' => true, 'status' => 'active']);
    $user->schools()->attach($switchedTo, ['is_primary' => false, 'status' => 'active']);

    UserSessionPreference::create([
        'user_id' => $user->id,
        'school_id' => $switchedTo->id,
        'updated_at' => now(),
    ]);

    expect(ActiveSchoolResolver::resolveId($user))->toBe($switchedTo->id);
});

it('ignores a stale preference for a school the user is no longer assigned to', function (): void {
    $user = User::factory()->create();
    $primary = School::factory()->create();
    $noLongerAssigned = School::factory()->create();
    $user->schools()->attach($primary, ['is_primary' => true, 'status' => 'active']);

    UserSessionPreference::create([
        'user_id' => $user->id,
        'school_id' => $noLongerAssigned->id,
        'updated_at' => now(),
    ]);

    expect(ActiveSchoolResolver::resolveId($user))->toBe($primary->id);
});

it('uses the newest preference when the user has switched more than once', function (): void {
    $user = User::factory()->create();
    $first = School::factory()->create();
    $second = School::factory()->create();
    $user->schools()->attach($first, ['is_primary' => true, 'status' => 'active']);
    $user->schools()->attach($second, ['is_primary' => false, 'status' => 'active']);

    // Plain query-builder inserts bypass Eloquent's automatic
    // `updateTimestamps()` (which would otherwise overwrite both rows'
    // `updated_at` with "now" regardless of what's passed to `create()`),
    // so the explicit values here actually stick.
    UserSessionPreference::query()->insert(['user_id' => $user->id, 'school_id' => $first->id, 'created_at' => now(), 'updated_at' => now()->subMinute()]);
    UserSessionPreference::query()->insert(['user_id' => $user->id, 'school_id' => $second->id, 'created_at' => now(), 'updated_at' => now()]);

    expect(ActiveSchoolResolver::resolveId($user))->toBe($second->id);
});
