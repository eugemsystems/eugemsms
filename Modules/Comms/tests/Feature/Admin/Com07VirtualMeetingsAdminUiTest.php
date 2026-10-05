<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSession;
use Modules\Academic\Models\PeriodSlot;
use Modules\Academic\Models\PeriodStructure;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Comms\Domain\Actions\BookConsultationSlotAction;
use Modules\Comms\Domain\Actions\RegisterMeetingProviderAction;
use Modules\Comms\Domain\Actions\ScheduleMeetingAction;
use Modules\Comms\Domain\DataObjects\ScheduleMeetingData;
use Modules\Comms\Domain\Events\UnauthorisedWaitingRoomOverride;
use Modules\Comms\Domain\Events\WaitingRoomOverrideDisabled;
use Modules\Comms\Livewire\Consultations\Windows;
use Modules\Comms\Livewire\Meetings\AttendanceReview;
use Modules\Comms\Livewire\Meetings\Index;
use Modules\Comms\Livewire\Meetings\Providers;
use Modules\Comms\Livewire\Meetings\Recordings;
use Modules\Comms\Models\ConsultationBooking;
use Modules\Comms\Models\ConsultationWindow;
use Modules\Comms\Models\MeetingAttendance;
use Modules\Comms\Models\MeetingProvider;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * Book I COM-07 admin-UI pass. Own, distinctly-named helpers.
 *
 * @return array<string, mixed>
 */
function com07AdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();

    return compact('school', 'year', 'term');
}

/**
 * @param  array<string, mixed>  $f
 */
function com07AdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $parts = explode('.', $permissionName);
        $action = end($parts);

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($parts[0]), 'resource' => count($parts) > 2 ? $parts[1] : $action, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

/**
 * @param  array<string, mixed>  $f
 */
function com07AdminSlot(array $f): TimetableSlot
{
    $structure = PeriodStructure::factory()->create(['school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id]);
    $periodSlot = PeriodSlot::factory()->create(['school_id' => $f['school']->id, 'structure_id' => $structure->id]);
    $timetable = Timetable::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id,
        'term_id' => $f['term']->id, 'structure_id' => $structure->id,
    ]);

    return TimetableSlot::factory()->create([
        'school_id' => $f['school']->id, 'timetable_id' => $timetable->id, 'term_id' => $f['term']->id,
        'period_slot_id' => $periodSlot->id,
        'subject_id' => Subject::factory()->create(['school_id' => $f['school']->id])->id,
        'staff_id' => Staff::factory()->for($f['school'])->create()->id,
    ]);
}

/**
 * @param  array<string, mixed>  $f
 * @return array{provider: MeetingProvider, meeting: ScheduledMeeting}
 */
function com07AdminMeeting(array $f, string $type = 'staff_meeting', ?int $hostStaffId = null, ?int $slotId = null): array
{
    $provider = MeetingProvider::where('school_id', $f['school']->id)->first()
        ?? app(RegisterMeetingProviderAction::class)->execute($f['school']->id, 'zoom', '{"client_secret":"s3cret"}');

    $meeting = app(ScheduleMeetingAction::class)->execute(new ScheduleMeetingData(
        schoolId: $f['school']->id, termId: $f['term']->id, meetingType: $type, providerId: $provider->id,
        topic: 'Test meeting', startsAt: Carbon::now()->addDay(), durationMinutes: 60,
        hostStaffId: $hostStaffId, timetableSlotId: $slotId,
    ));

    return compact('provider', 'meeting');
}

it('refuses every COM-07 screen to a user without its permission', function (string $component): void {
    $f = com07AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test($component, ['school' => $f['school']])->assertForbidden();
})->with([
    'providers' => Providers::class,
    'schedule' => Index::class,
    'consultations' => Windows::class,
    'attendance' => AttendanceReview::class,
    'recordings' => Recordings::class,
]);

it('renders every COM-07 screen for a fully-permissioned user', function (): void {
    $f = com07AdminFixture();
    $user = com07AdminUser($f, 'meetings.manage', 'meetings.view', 'meetings.consultation.manage', 'meetings.recording.view', 'academic.attendance.mark');

    foreach ([Providers::class, Index::class, Windows::class, AttendanceReview::class, Recordings::class] as $component) {
        Livewire::actingAs($user)->test($component, ['school' => $f['school']])->assertOk();
    }
});

it('stores provider credentials encrypted, never shows them, and replaces them on re-registration (BR-COM-07-001)', function (): void {
    $f = com07AdminFixture();
    $admin = com07AdminUser($f, 'meetings.manage');

    $component = Livewire::actingAs($admin)->test(Providers::class, ['school' => $f['school']])
        ->set('provider', 'zoom')
        ->set('credentials', '{"client_secret":"zoom-secret-123"}')
        ->set('accountEmail', 'school@example.com')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSee('school@example.com')
        ->assertDontSee('zoom-secret-123');

    $stored = MeetingProvider::where('school_id', $f['school']->id)->firstOrFail();
    expect($stored->credentials)->toContain('zoom-secret-123')
        ->and($stored->getRawOriginal('credentials'))->not->toContain('zoom-secret-123');

    $component->set('credentials', '{"client_secret":"rotated"}')->call('register')->assertHasNoErrors();
    expect(MeetingProvider::where('school_id', $f['school']->id)->count())->toBe(1)
        ->and($stored->fresh()->credentials)->toContain('rotated');

    $component->set('credentials', 'not json')->call('register')->assertHasErrors(['credentials']);
});

it('schedules a meeting and never loads host credentials into the schedule (AC-COM-07-001)', function (): void {
    $f = com07AdminFixture();
    $manager = com07AdminUser($f, 'meetings.manage', 'meetings.view');
    $provider = app(RegisterMeetingProviderAction::class)->execute($f['school']->id, 'zoom', '{"k":"v"}');
    $host = Staff::factory()->for($f['school'])->create();

    $component = Livewire::actingAs($manager)->test(Index::class, ['school' => $f['school']])
        ->set('meetingType', 'staff_meeting')
        ->set('providerId', $provider->id)
        ->set('topic', 'Staff briefing')
        ->set('startsAt', now()->addDay()->format('Y-m-d\TH:i'))
        ->set('hostStaffId', $host->id)
        ->call('schedule')
        ->assertHasNoErrors();

    $meeting = ScheduledMeeting::where('school_id', $f['school']->id)->firstOrFail();
    expect($meeting->host_url)->not->toBeNull();

    $listed = $component->viewData('meetings')->first();
    expect(array_keys($listed->getAttributes()))->not->toContain('host_url')->not->toContain('passcode');
    $component->assertDontSee($meeting->host_url)->assertDontSee((string) $meeting->passcode);
});

it('requires a timetable slot for an online lesson and keeps its waiting room on (BR-COM-07-002/003)', function (): void {
    $f = com07AdminFixture();
    $manager = com07AdminUser($f, 'meetings.manage', 'meetings.view');
    $provider = app(RegisterMeetingProviderAction::class)->execute($f['school']->id, 'zoom', '{"k":"v"}');
    $slot = com07AdminSlot($f);

    $component = Livewire::actingAs($manager)->test(Index::class, ['school' => $f['school']])
        ->set('meetingType', 'online_lesson')
        ->set('providerId', $provider->id)
        ->set('topic', 'Maths')
        ->set('startsAt', now()->addDay()->format('Y-m-d\TH:i'))
        ->set('waitingRoomEnabled', false)
        ->call('schedule')
        ->assertHasErrors(['timetableSlotId']);

    expect(ScheduledMeeting::where('school_id', $f['school']->id)->count())->toBe(0);

    $component->set('timetableSlotId', $slot->id)->call('schedule')->assertHasNoErrors();

    $meeting = ScheduledMeeting::where('school_id', $f['school']->id)->firstOrFail();
    expect($meeting->waiting_room_enabled)->toBeTrue()->and($meeting->timetable_slot_id)->toBe($slot->id);
});

it('cancels a scheduled meeting only for a manager', function (): void {
    $f = com07AdminFixture();
    $manager = com07AdminUser($f, 'meetings.manage', 'meetings.view');
    $viewer = com07AdminUser($f, 'meetings.view');
    ['meeting' => $meeting] = com07AdminMeeting($f);

    Livewire::actingAs($viewer)->test(Index::class, ['school' => $f['school']])->call('cancel', $meeting->id)->assertForbidden();
    expect($meeting->fresh()->status)->toBe('scheduled');

    Livewire::actingAs($manager)->test(Index::class, ['school' => $f['school']])->call('cancel', $meeting->id);
    expect($meeting->fresh()->status)->toBe('cancelled');
});

it('shows host credentials to the meeting’s host only (BR-COM-07-001)', function (): void {
    $f = com07AdminFixture();
    $hostUser = com07AdminUser($f, 'meetings.view');
    $otherUser = com07AdminUser($f, 'meetings.view');
    $host = Staff::factory()->for($f['school'])->create(['user_id' => $hostUser->id]);
    ['meeting' => $meeting] = com07AdminMeeting($f, hostStaffId: $host->id);

    $asHost = Livewire::actingAs($hostUser)->test(Index::class, ['school' => $f['school']])->call('revealHostCredentials', $meeting->id);
    expect($asHost->get('revealed')['host_url'])->toBe($meeting->host_url);
    $asHost->call('hideHostCredentials');
    expect($asHost->get('revealed'))->toBeNull();

    $asOther = Livewire::actingAs($otherUser)->test(Index::class, ['school' => $f['school']])
        ->call('revealHostCredentials', $meeting->id)
        ->assertDispatched('toast', variant: 'danger');
    expect($asOther->get('revealed'))->toBeNull();
});

it('logs and refuses a waiting-room override without the explicit permission, and logs an approved one (AC-COM-07-005)', function (): void {
    $f = com07AdminFixture();
    $plain = com07AdminUser($f, 'meetings.view');
    $approver = com07AdminUser($f, 'meetings.view', 'meetings.waiting_room.override');
    ['meeting' => $meeting] = com07AdminMeeting($f, 'online_lesson', slotId: com07AdminSlot($f)->id);
    Event::fake([UnauthorisedWaitingRoomOverride::class, WaitingRoomOverrideDisabled::class]);

    Livewire::actingAs($plain)->test(Index::class, ['school' => $f['school']])
        ->call('disableWaitingRoom', $meeting->id)
        ->assertDispatched('toast', variant: 'danger');
    expect($meeting->fresh()->waiting_room_enabled)->toBeTrue();
    Event::assertDispatched(UnauthorisedWaitingRoomOverride::class);

    Livewire::actingAs($approver)->test(Index::class, ['school' => $f['school']])->call('disableWaitingRoom', $meeting->id);
    expect($meeting->fresh()->waiting_room_enabled)->toBeFalse();
    Event::assertDispatched(WaitingRoomOverrideDisabled::class);
});

it('creates a consultation window and frees a slot when its booking is cancelled (BR-COM-07-005)', function (): void {
    $f = com07AdminFixture();
    $manager = com07AdminUser($f, 'meetings.consultation.manage');
    $teacher = Staff::factory()->for($f['school'])->create();

    $component = Livewire::actingAs($manager)->test(Windows::class, ['school' => $f['school']])
        ->set('staffId', $teacher->id)
        ->set('eventName', 'Term 2 Parents Evening')
        ->set('availableFrom', now()->addWeek()->setTime(16, 0)->format('Y-m-d\TH:i'))
        ->set('availableTo', now()->addWeek()->setTime(18, 0)->format('Y-m-d\TH:i'))
        ->call('createWindow')
        ->assertHasNoErrors();

    $window = ConsultationWindow::where('school_id', $f['school']->id)->firstOrFail();
    $slot = $window->available_from->copy();
    $guardian = Guardian::factory()->for($f['school'])->create();
    $student = Student::factory()->for($f['school'])->create();
    $booking = app(BookConsultationSlotAction::class)->execute($window->id, $guardian->id, $student->id, $slot);

    SchoolContext::clear();
    $component->call('cancelBooking', $booking->id);
    expect($booking->fresh()->status)->toBe('cancelled');

    expect(app(BookConsultationSlotAction::class)->execute($window->id, $guardian->id, $student->id, $slot)->status)->toBe('booked');
});

it('rejects a window that ends before it starts and another school’s booking', function (): void {
    $f = com07AdminFixture();
    $manager = com07AdminUser($f, 'meetings.consultation.manage');
    $teacher = Staff::factory()->for($f['school'])->create();

    Livewire::actingAs($manager)->test(Windows::class, ['school' => $f['school']])
        ->set('staffId', $teacher->id)->set('eventName', 'Bad')
        ->set('availableFrom', now()->addWeek()->setTime(18, 0)->format('Y-m-d\TH:i'))
        ->set('availableTo', now()->addWeek()->setTime(16, 0)->format('Y-m-d\TH:i'))
        ->call('createWindow')
        ->assertHasErrors(['availableTo']);

    $other = School::factory()->create();
    $foreign = ConsultationBooking::factory()->create(['school_id' => $other->id]);
    SchoolContext::set($f['school']);

    expect(fn () => Livewire::actingAs($manager)->test(Windows::class, ['school' => $f['school']])->call('cancelBooking', $foreign->id))
        ->toThrow(ModelNotFoundException::class);
});

/**
 * @param  array<string, mixed>  $f
 * @return array{meeting: ScheduledMeeting, session: AttendanceSession, student: Student}
 */
function com07AdminLesson(array $f): array
{
    $slot = com07AdminSlot($f);
    ['meeting' => $meeting] = com07AdminMeeting($f, 'online_lesson', slotId: $slot->id);
    $session = AttendanceSession::factory()->for($f['school'])->create(['timetable_slot_id' => $slot->id, 'expected_count' => 2, 'status' => 'pending']);
    $student = Student::factory()->for($f['school'])->create();

    MeetingAttendance::factory()->for($f['school'])->create([
        'meeting_id' => $meeting->id, 'participant_identifier' => 'parent@example.com', 'student_id' => $student->id,
        'match_confidence' => 'exact', 'joined_at' => now(), 'left_at' => now()->addMinutes(24), 'duration_seconds' => 24 * 60,
    ]);
    MeetingAttendance::factory()->for($f['school'])->create([
        'meeting_id' => $meeting->id, 'participant_identifier' => 'unknown@example.com', 'student_id' => null,
        'match_confidence' => 'unmatched', 'joined_at' => now(), 'left_at' => now()->addMinutes(5), 'duration_seconds' => 300,
    ]);

    return compact('meeting', 'session', 'student');
}

it('flags a below-threshold learner with no default status and lists the unmatched participant (AC-COM-07-003, BR-COM-07-008)', function (): void {
    $f = com07AdminFixture();
    $teacher = com07AdminUser($f, 'academic.attendance.mark');
    $l = com07AdminLesson($f);

    $component = Livewire::actingAs($teacher)->test(AttendanceReview::class, ['school' => $f['school']])
        ->set('meetingId', $l['meeting']->id)
        ->assertSee('below threshold')
        ->assertSee('unmatched');

    $suggestions = collect($component->get('suggestions'));
    $matched = $suggestions->firstWhere('student_id', $l['student']->id);

    expect($matched['percent'])->toBe(40)->and($matched['below'])->toBeTrue()
        ->and($component->get('decisions')[$matched['meeting_attendance_id']])->toBe('')
        ->and(AttendanceRecord::count())->toBe(0);
});

it('writes only the statuses the teacher chose, only for matched learners (AC-COM-07-002, BR-COM-07-007)', function (): void {
    $f = com07AdminFixture();
    $teacher = com07AdminUser($f, 'academic.attendance.mark');
    $l = com07AdminLesson($f);

    $component = Livewire::actingAs($teacher)->test(AttendanceReview::class, ['school' => $f['school']])->set('meetingId', $l['meeting']->id);
    $matchedId = collect($component->get('suggestions'))->firstWhere('student_id', $l['student']->id)['meeting_attendance_id'];

    $component->call('confirm')->assertHasErrors(['decisions']);
    expect(AttendanceRecord::count())->toBe(0);

    SchoolContext::clear();
    $component->set("decisions.{$matchedId}", 'late')->call('confirm')->assertHasNoErrors();

    $records = AttendanceRecord::where('session_id', $l['session']->id)->get();
    expect($records)->toHaveCount(1)
        ->and($records->first()->student_id)->toBe($l['student']->id)
        ->and($records->first()->status)->toBe('late');
});

it('ignores a tampered session id and writes to the meeting’s own session', function (): void {
    $f = com07AdminFixture();
    $teacher = com07AdminUser($f, 'academic.attendance.mark');
    $l = com07AdminLesson($f);
    $foreignSession = AttendanceSession::factory()->create();
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($teacher)->test(AttendanceReview::class, ['school' => $f['school']])->set('meetingId', $l['meeting']->id);
    $matchedId = collect($component->get('suggestions'))->firstWhere('student_id', $l['student']->id)['meeting_attendance_id'];

    $component->set('sessionId', $foreignSession->id)->set("decisions.{$matchedId}", 'present')->call('confirm');

    expect(AttendanceRecord::where('session_id', $foreignSession->id)->count())->toBe(0)
        ->and(AttendanceRecord::where('session_id', $l['session']->id)->count())->toBe(1);
});

it('reports a meeting that has no attendance session instead of failing', function (): void {
    $f = com07AdminFixture();
    $teacher = com07AdminUser($f, 'academic.attendance.mark');
    ['meeting' => $meeting] = com07AdminMeeting($f, 'online_lesson', slotId: com07AdminSlot($f)->id);

    Livewire::actingAs($teacher)->test(AttendanceReview::class, ['school' => $f['school']])
        ->set('meetingId', $meeting->id)
        ->assertHasErrors(['meetingId']);
});

it('lists recordings and purges only expired ones, only for a manager (BR-COM-07-004)', function (): void {
    $f = com07AdminFixture();
    $viewer = com07AdminUser($f, 'meetings.recording.view');
    $manager = com07AdminUser($f, 'meetings.recording.view', 'meetings.manage');
    ['meeting' => $expired] = com07AdminMeeting($f);
    ['meeting' => $current] = com07AdminMeeting($f);
    $expired->update(['recording_enabled' => true, 'recording_url' => 'https://rec.example/old', 'recording_expires_on' => now()->subDay()]);
    $current->update(['recording_enabled' => true, 'recording_url' => 'https://rec.example/new', 'recording_expires_on' => now()->addMonth()]);

    Livewire::actingAs($viewer)->test(Recordings::class, ['school' => $f['school']])
        ->assertSee('https://rec.example/old')
        ->assertSee('https://rec.example/new')
        ->call('purgeExpired')
        ->assertForbidden();

    Livewire::actingAs($manager)->test(Recordings::class, ['school' => $f['school']])->call('purgeExpired');

    expect($expired->fresh()->recording_url)->toBeNull()
        ->and($current->fresh()->recording_url)->toBe('https://rec.example/new');
});
