<?php

use App\Models\User;
use Modules\Comms\Domain\Actions\BuildLearnerDashboardAction;
use Modules\Comms\Domain\Actions\BuildParentDashboardAction;
use Modules\Comms\Domain\Actions\BuildStaffDashboardAction;
use Modules\Comms\Domain\Actions\IsPortalAccountEligibleAction;
use Modules\Comms\Domain\Actions\RecordOnboardingStepAction;
use Modules\Comms\Domain\Actions\RegisterPortalDeviceAction;
use Modules\Comms\Domain\Actions\RequestOfflineSyncAction;
use Modules\Comms\Domain\Actions\ResolveDeepLinkAction;
use Modules\Comms\Domain\Actions\RevokePortalDeviceAction;
use Modules\Comms\Domain\Actions\SetDevicePinAction;
use Modules\Comms\Domain\Actions\SetWidgetConfigurationAction;
use Modules\Comms\Domain\Actions\SkipOnboardingAction;
use Modules\Comms\Domain\DataObjects\RegisterPortalDeviceData;
use Modules\Comms\Domain\Exceptions\PersonaNotFoundException;
use Modules\Comms\Domain\Exceptions\TimeLockedResourceNotSyncableException;
use Modules\Comms\Models\OnboardingProgress;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Notification;
use Modules\Core\Models\PersonalAccessToken;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolModule;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Wallet\Models\StudentWallet;

/**
 * @return array{school: School, user: User, guardian: Guardian, student: Student}
 */
function com0345ParentFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);

    $user = User::factory()->create();
    $guardian = Guardian::factory()->for($school)->create(['user_id' => $user->id]);
    $student = Student::factory()->for($school)->create();
    StudentGuardian::factory()->for($school)->create([
        'student_id' => $student->id, 'guardian_id' => $guardian->id, 'status' => 'active',
    ]);

    return compact('school', 'user', 'guardian', 'student');
}

it('assembles a parent dashboard in one call with tile-only widget summaries and the guardian\'s children (AC-COM-03-001/008)', function (): void {
    $f = com0345ParentFixture();

    Notification::factory()->create([
        'school_id' => $f['school']->id,
        'recipient_type' => 'guardian', 'recipient_id' => $f['guardian']->id, 'channel' => 'in_app', 'read_at' => null,
    ]);

    $result = app(BuildParentDashboardAction::class)->execute($f['user'], $f['school']->id);

    expect($result->children)->toHaveCount(1)
        ->and($result->children[0]['id'])->toBe($f['student']->id)
        ->and($result->unreadNotifications)->toBe(1);

    foreach ($result->widgets as $widget) {
        expect($widget->summary)->not->toHaveKey('items');
    }
});

it('throws when the authenticated user has no linked guardian record for the school', function (): void {
    $school = School::factory()->create();
    SchoolContext::set($school);
    $user = User::factory()->create();

    expect(fn () => app(BuildParentDashboardAction::class)->execute($user, $school->id))
        ->toThrow(PersonaNotFoundException::class);
});

it('hides a widget entirely, not empty, when its owning module is disabled for the school (AC-COM-03-002)', function (): void {
    $f = com0345ParentFixture();

    StudentWallet::factory()->for($f['school'])->create(['student_id' => $f['student']->id, 'balance_minor' => 5000]);
    SchoolModule::factory()->for($f['school'])->create(['module_code' => 'FIN-14', 'is_enabled' => false]);

    $result = app(BuildParentDashboardAction::class)->execute($f['user'], $f['school']->id);

    $keys = collect($result->widgets)->pluck('key');
    expect($keys)->not->toContain('wallet_balance_parent');
});

it('has no eligible portal account below the configured minimum grade, and is eligible at or above it (AC-COM-03-003)', function (): void {
    $school = School::factory()->create();
    SchoolContext::set($school);

    $belowGrade = GradeLevel::factory()->for($school)->create(['ordinal' => 2]);
    $atGrade = GradeLevel::factory()->for($school)->create(['ordinal' => 5]);

    $ineligible = Student::factory()->for($school)->create(['grade_level_id' => $belowGrade->id]);
    $eligible = Student::factory()->for($school)->create(['grade_level_id' => $atGrade->id]);

    expect(app(IsPortalAccountEligibleAction::class)->execute($ineligible->id))->toBeFalse()
        ->and(app(IsPortalAccountEligibleAction::class)->execute($eligible->id))->toBeTrue();
});

it('always shows the safeguarding "tell someone" entry point on a learner dashboard, unremovable by widget configuration (AC-COM-03-004)', function (): void {
    $school = School::factory()->create();
    SchoolContext::set($school);
    $grade = GradeLevel::factory()->for($school)->create(['ordinal' => 10]);
    $user = User::factory()->create();
    $student = Student::factory()->for($school)->create(['user_id' => $user->id, 'grade_level_id' => $grade->id]);

    // Attempt to disable every registered learner widget for the school — the
    // safeguarding entry point is never a registry widget, so this cannot touch it.
    app(SetWidgetConfigurationAction::class)->execute($school->id, 'wallet_balance_learner', 'learner', isEnabled: false);

    $result = app(BuildLearnerDashboardAction::class)->execute($user, $school->id);

    $keys = collect($result->widgets)->pluck('key');
    expect($keys)->toContain('safeguarding_tell_someone');

    $entry = collect($result->widgets)->firstWhere('key', 'safeguarding_tell_someone');
    expect($entry->summary['always_visible'])->toBeTrue();
});

it('assembles a staff dashboard for a linked staff user', function (): void {
    $school = School::factory()->create();
    SchoolContext::set($school);
    $user = User::factory()->create();
    $staff = Staff::factory()->for($school)->create(['user_id' => $user->id]);

    Notification::factory()->create([
        'school_id' => $school->id,
        'recipient_type' => 'staff', 'recipient_id' => $staff->id, 'channel' => 'in_app', 'read_at' => null,
    ]);

    $result = app(BuildStaffDashboardAction::class)->execute($user, $school->id);

    expect($result->unreadNotifications)->toBe(1);
});

it('refuses to register an offline sync manifest for exam papers outright (AC-COM-03-005)', function (): void {
    $school = School::factory()->create();
    SchoolContext::set($school);
    $user = User::factory()->create();

    expect(fn () => app(RequestOfflineSyncAction::class)->execute($user->id, 'exam_papers'))
        ->toThrow(TimeLockedResourceNotSyncableException::class);

    $manifest = app(RequestOfflineSyncAction::class)->execute($user->id, 'timetable');
    expect($manifest->data_category)->toBe('timetable');
});

it('revoking a portal device clears its push token immediately and revokes matching access tokens (AC-COM-03-006)', function (): void {
    $school = School::factory()->create();
    SchoolContext::set($school);
    $user = User::factory()->create();

    $device = app(RegisterPortalDeviceAction::class)->execute(new RegisterPortalDeviceData(
        userId: $user->id, deviceId: 'device-abc', platform: 'android', pushToken: 'push-token-1',
    ));

    $token = $user->createToken('Test Device', ['*']);
    $token->accessToken->forceFill(['device_id' => 'device-abc', 'school_id' => $school->id])->save();

    // An unrelated device's token must survive the revocation untouched.
    $otherToken = $user->createToken('Other Device', ['*']);
    $otherToken->accessToken->forceFill(['device_id' => 'device-xyz', 'school_id' => $school->id])->save();

    $revoked = app(RevokePortalDeviceAction::class)->execute($device->id);

    expect($revoked->is_active)->toBeFalse()
        ->and($revoked->push_token)->toBeNull()
        ->and($revoked->revoked_at)->not->toBeNull();

    expect(PersonalAccessToken::find($token->accessToken->id)->isRevoked())->toBeTrue()
        ->and(PersonalAccessToken::find($otherToken->accessToken->id)->isRevoked())->toBeFalse();
});

it('sets an app-level PIN independently of the session token, which stays valid (AC-COM-03-007)', function (): void {
    $school = School::factory()->create();
    SchoolContext::set($school);
    $user = User::factory()->create();

    $device = app(RegisterPortalDeviceAction::class)->execute(new RegisterPortalDeviceData(
        userId: $user->id, deviceId: 'device-pin', platform: 'ios',
    ));
    $token = $user->createToken('Test Device', ['*']);
    $token->accessToken->forceFill(['device_id' => 'device-pin', 'school_id' => $school->id])->save();

    $updated = app(SetDevicePinAction::class)->execute($device->id, '1234', minLength: 4);

    expect($updated->hasAppLock())->toBeTrue()
        ->and($updated->refresh()->app_pin_hash)->not->toBeNull()
        ->and(PersonalAccessToken::find($token->accessToken->id)->isRevoked())->toBeFalse();
});

it('a skipped onboarding step remains available afterward, without locking the record (BR-COM-03-009)', function (): void {
    $school = School::factory()->create();
    SchoolContext::set($school);
    $user = User::factory()->create();

    app(RecordOnboardingStepAction::class)->execute($user->id, 'parent', 'welcome');
    $skipped = app(SkipOnboardingAction::class)->execute($user->id, 'parent');

    expect($skipped->skipped_at)->not->toBeNull()
        ->and($skipped->hasCompletedStep('welcome'))->toBeTrue();

    $resumed = app(RecordOnboardingStepAction::class)->execute($user->id, 'parent', 'link_children', isFinalStep: true);

    expect($resumed->hasCompletedStep('link_children'))->toBeTrue()
        ->and($resumed->completed_at)->not->toBeNull()
        ->and(OnboardingProgress::where('user_id', $user->id)->count())->toBe(1);
});

it('resolves a registered deep link to its real screen when the caller permits it', function (): void {
    $school = School::factory()->create();
    SchoolContext::set($school);

    $result = app(ResolveDeepLinkAction::class)->execute('invoice', 42, fn (): bool => true);

    expect($result->degraded)->toBeFalse()
        ->and($result->screen)->toBe('/finance/invoices/42');
});

it('degrades an unregistered deep link type to the fallback screen instead of a blank error (BR-COM-03-011)', function (): void {
    $result = app(ResolveDeepLinkAction::class)->execute('some_unregistered_type', 1, fn (): bool => true);

    expect($result->degraded)->toBeTrue()
        ->and($result->screen)->toBe('/');
});

it('degrades a permission refusal to the registered fallback screen, not a blank error (BR-COM-03-011)', function (): void {
    $result = app(ResolveDeepLinkAction::class)->execute('student', 7, fn (): bool => false);

    expect($result->degraded)->toBeTrue()
        ->and($result->screen)->toBe('/students');
});
