<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Academic\Models\AttendanceRecord;
use Modules\Academic\Models\AttendanceSession;
use Modules\Academic\Models\PeriodSlot;
use Modules\Academic\Models\PeriodStructure;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Comms\Domain\Actions\BookConsultationSlotAction;
use Modules\Comms\Domain\Actions\CancelScheduledMeetingAction;
use Modules\Comms\Domain\Actions\ConfirmAttendanceReconciliationAction;
use Modules\Comms\Domain\Actions\CreateConsultationWindowAction;
use Modules\Comms\Domain\Actions\DisableWaitingRoomOverrideAction;
use Modules\Comms\Domain\Actions\IngestMeetingWebhookAction;
use Modules\Comms\Domain\Actions\PreviewAttendanceReconciliationAction;
use Modules\Comms\Domain\Actions\PurgeExpiredRecordingsAction;
use Modules\Comms\Domain\Actions\RegisterMeetingProviderAction;
use Modules\Comms\Domain\Actions\ResolveMeetingHostCredentialsAction;
use Modules\Comms\Domain\Actions\ResolveMeetingJoinUrlAction;
use Modules\Comms\Domain\Actions\ScheduleMeetingAction;
use Modules\Comms\Domain\DataObjects\IngestMeetingWebhookData;
use Modules\Comms\Domain\DataObjects\ScheduleMeetingData;
use Modules\Comms\Domain\Exceptions\ConsultationSlotAlreadyBookedException;
use Modules\Comms\Domain\Exceptions\MeetingHostCredentialsNotAccessibleException;
use Modules\Comms\Domain\Exceptions\WaitingRoomOverrideRequiresApprovalException;
use Modules\Comms\Models\ConsultationBooking;
use Modules\Comms\Models\MeetingAttendance;
use Modules\Comms\Models\MeetingProvider;
use Modules\Comms\Models\MeetingWebhookEvent;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * @return array{school: School, user: User, year: AcademicYear, term: Term}
 */
function com07Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $user = User::factory()->create();

    return compact('school', 'user', 'year', 'term');
}

/**
 * `TimetableSlot::factory()`'s own definition() builds a fully
 * independent school/year/term/structure/period-slot ecosystem
 * internally (Book E ACA-03's own factory, not this book's to
 * rewrite) — passing `->for($school)` alone only rebinds the slot's
 * own `school_id`, not the whole nested chain, so every FK here is
 * supplied explicitly and tied to THIS fixture's own school/term
 * instead, avoiding that chain entirely.
 */
function com07TimetableSlot(array $f): TimetableSlot
{
    $structure = PeriodStructure::factory()->create(['school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id]);
    $periodSlot = PeriodSlot::factory()->create(['school_id' => $f['school']->id, 'structure_id' => $structure->id]);
    $timetable = Timetable::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id,
        'term_id' => $f['term']->id, 'structure_id' => $structure->id,
    ]);
    $subject = Subject::factory()->create(['school_id' => $f['school']->id]);
    $staff = Staff::factory()->for($f['school'])->create();

    return TimetableSlot::factory()->create([
        'school_id' => $f['school']->id, 'timetable_id' => $timetable->id, 'term_id' => $f['term']->id,
        'period_slot_id' => $periodSlot->id, 'subject_id' => $subject->id, 'staff_id' => $staff->id,
    ]);
}

it('registers a meeting provider and schedules a generic meeting through it', function (): void {
    $f = com07Fixture();
    $provider = app(RegisterMeetingProviderAction::class)->execute($f['school']->id, 'zoom', 's2s-oauth-secret');

    $meeting = app(ScheduleMeetingAction::class)->execute(new ScheduleMeetingData(
        schoolId: $f['school']->id, termId: $f['term']->id, meetingType: 'staff_meeting',
        providerId: $provider->id, topic: 'Staff Briefing', startsAt: Carbon::parse('2027-02-01 08:00'),
        durationMinutes: 30,
    ));

    expect($meeting->provider_meeting_id)->not->toBeNull()
        ->and($meeting->join_url)->not->toBeNull()
        ->and($meeting->status)->toBe('scheduled');
});

it('resolves only the join link publicly, never host credentials, but the real host can fetch them (AC-COM-07-001, BR-COM-07-001)', function (): void {
    $f = com07Fixture();
    $provider = app(RegisterMeetingProviderAction::class)->execute($f['school']->id, 'zoom', 'secret');
    $hostUser = User::factory()->create();
    $host = Staff::factory()->for($f['school'])->create(['user_id' => $hostUser->id]);
    $otherUser = User::factory()->create();

    $meeting = app(ScheduleMeetingAction::class)->execute(new ScheduleMeetingData(
        schoolId: $f['school']->id, termId: $f['term']->id, meetingType: 'staff_meeting',
        providerId: $provider->id, topic: 'Board Meeting', startsAt: Carbon::parse('2027-02-01 08:00'),
        durationMinutes: 60, hostStaffId: $host->id,
    ));

    $joinUrl = app(ResolveMeetingJoinUrlAction::class)->execute($meeting->id);
    expect($joinUrl)->toBe($meeting->join_url)
        ->and($joinUrl)->not->toContain($meeting->host_url ?? '__never__');

    expect(fn () => app(ResolveMeetingHostCredentialsAction::class)->execute($meeting->id, $otherUser->id))
        ->toThrow(MeetingHostCredentialsNotAccessibleException::class);

    $credentials = app(ResolveMeetingHostCredentialsAction::class)->execute($meeting->id, $hostUser->id);
    expect($credentials['host_url'])->toBe($meeting->host_url)
        ->and($credentials['passcode'])->toBe($meeting->passcode);
});

it('requires explicit approval to disable the waiting room on a learner-facing meeting, and logs it (AC-COM-07-005, BR-COM-07-003)', function (): void {
    $f = com07Fixture();
    $provider = app(RegisterMeetingProviderAction::class)->execute($f['school']->id, 'zoom', 'secret');
    $slot = com07TimetableSlot($f);

    $meeting = app(ScheduleMeetingAction::class)->execute(new ScheduleMeetingData(
        schoolId: $f['school']->id, termId: $f['term']->id, meetingType: 'online_lesson',
        providerId: $provider->id, topic: 'Maths Lesson', startsAt: Carbon::parse('2027-02-01 08:00'),
        durationMinutes: 40, timetableSlotId: $slot->id,
    ));

    expect($meeting->waiting_room_enabled)->toBeTrue();

    expect(fn () => app(DisableWaitingRoomOverrideAction::class)->execute($meeting->id, $f['user']->id, null))
        ->toThrow(WaitingRoomOverrideRequiresApprovalException::class);
    expect($meeting->fresh()->waiting_room_enabled)->toBeTrue();

    $overridden = app(DisableWaitingRoomOverrideAction::class)->execute($meeting->id, $f['user']->id, $f['user']->id);
    expect($overridden->waiting_room_enabled)->toBeFalse();
});

/**
 * @return array{provider: MeetingProvider, slot: TimetableSlot, meeting: ScheduledMeeting, session: AttendanceSession, guardian: Guardian, student: Student}
 */
function com07OnlineLessonFixture(array $f, int $durationMinutes = 60): array
{
    $provider = app(RegisterMeetingProviderAction::class)->execute($f['school']->id, 'zoom', 'secret');
    $slot = com07TimetableSlot($f);

    $meeting = app(ScheduleMeetingAction::class)->execute(new ScheduleMeetingData(
        schoolId: $f['school']->id, termId: $f['term']->id, meetingType: 'online_lesson',
        providerId: $provider->id, topic: 'Lesson', startsAt: Carbon::parse('2027-02-01 08:00'),
        durationMinutes: $durationMinutes, timetableSlotId: $slot->id,
    ));

    $session = AttendanceSession::factory()->for($f['school'])->create([
        'timetable_slot_id' => $slot->id, 'expected_count' => 1, 'status' => 'pending',
    ]);

    $student = Student::factory()->for($f['school'])->create();
    $guardian = Guardian::factory()->for($f['school'])->create(['email' => 'parent@example.com', 'primary_phone' => '0771234567']);
    StudentGuardian::factory()->for($f['school'])->create([
        'student_id' => $student->id, 'guardian_id' => $guardian->id, 'status' => 'active',
    ]);

    return compact('provider', 'slot', 'meeting', 'session', 'guardian', 'student');
}

it('records raw join/leave from a provider webhook and matches an exact participant, without writing a locked ACA-04 record (AC-COM-07-002)', function (): void {
    $f = com07Fixture();
    $o = com07OnlineLessonFixture($f);

    $joinedPayload = json_encode([
        'event' => 'participant.joined', 'meeting_id' => $o['meeting']->provider_meeting_id,
        'participant' => 'parent@example.com', 'joined_at' => '2027-02-01T08:00:00Z',
    ]);
    $leftPayload = json_encode([
        'event' => 'participant.left', 'meeting_id' => $o['meeting']->provider_meeting_id,
        'participant' => 'parent@example.com', 'left_at' => '2027-02-01T08:24:00Z', 'duration_seconds' => 1440,
    ]);

    $first = app(IngestMeetingWebhookAction::class)->execute(new IngestMeetingWebhookData(
        provider: 'zoom', headers: ['X-Signature' => 'valid'], body: $joinedPayload,
    ));
    app(IngestMeetingWebhookAction::class)->execute(new IngestMeetingWebhookData(
        provider: 'zoom', headers: ['X-Signature' => 'valid'], body: $leftPayload,
    ));

    expect($first->processing_status)->toBe('processed')
        ->and($first->signature_valid)->toBeTrue();

    $attendance = MeetingAttendance::where('meeting_id', $o['meeting']->id)->firstOrFail();
    expect($attendance->student_id)->toBe($o['student']->id)
        ->and($attendance->match_confidence)->toBe('exact')
        ->and($attendance->duration_seconds)->toBe(1440);

    // A replayed identical payload is idempotent, not double-processed.
    $replay = app(IngestMeetingWebhookAction::class)->execute(new IngestMeetingWebhookData(
        provider: 'zoom', headers: ['X-Signature' => 'valid'], body: $joinedPayload,
    ));
    expect(MeetingWebhookEvent::where('provider', 'zoom')->count())->toBe(2)
        ->and($replay->id)->toBe($first->id); // the joined-payload's own row, found via its own hash, not a new one

    // Advisory only: no ACA-04 record exists yet from the webhook alone.
    expect(AttendanceRecord::where('session_id', $o['session']->id)->count())->toBe(0);
});

it('flags a participant present for under the threshold as below-threshold, and only the teacher\'s own confirmation writes ACA-04 (AC-COM-07-003, BR-COM-07-007/008)', function (): void {
    $f = com07Fixture();
    $o = com07OnlineLessonFixture($f, durationMinutes: 60);

    MeetingAttendance::factory()->for($f['school'])->create([
        'meeting_id' => $o['meeting']->id, 'participant_identifier' => 'parent@example.com',
        'student_id' => $o['student']->id, 'match_confidence' => 'exact',
        'joined_at' => now(), 'left_at' => now()->addMinutes(24), 'duration_seconds' => 24 * 60,
    ]);
    MeetingAttendance::factory()->for($f['school'])->create([
        'meeting_id' => $o['meeting']->id, 'participant_identifier' => 'unknown@example.com',
        'student_id' => null, 'match_confidence' => 'unmatched',
        'joined_at' => now(), 'left_at' => now()->addMinutes(5), 'duration_seconds' => 5 * 60,
    ]);

    ['session' => $session, 'suggestions' => $suggestions] = app(PreviewAttendanceReconciliationAction::class)->execute($o['meeting']->id);

    $matched = collect($suggestions)->firstWhere('studentId', $o['student']->id);
    $unmatched = collect($suggestions)->first(fn ($s) => $s->studentId === null);

    expect($matched->attendedPercent)->toBe(40)
        ->and($matched->belowThreshold)->toBeTrue()
        ->and($unmatched->note)->toContain('needs manual reconciliation');

    $result = app(ConfirmAttendanceReconciliationAction::class)->execute(
        $session->id,
        [['studentId' => $o['student']->id, 'status' => 'present', 'note' => $matched->note]],
        $f['user']->id,
    );

    $record = AttendanceRecord::where('session_id', $session->id)->where('student_id', $o['student']->id)->firstOrFail();
    expect($record->status)->toBe('present')
        ->and($record->note)->toContain('below the');
});

it('structurally prevents double-booking a consultation slot, offering the next available instead (AC-COM-07-004, BR-COM-07-005)', function (): void {
    $f = com07Fixture();
    $window = app(CreateConsultationWindowAction::class)->execute(
        schoolId: $f['school']->id, termId: $f['term']->id,
        staffId: Staff::factory()->for($f['school'])->create()->id,
        eventName: 'Parents Evening', availableFrom: Carbon::parse('2027-03-01 16:00'),
        availableTo: Carbon::parse('2027-03-01 18:00'), slotDurationMinutes: 10,
    );
    $guardianA = Guardian::factory()->for($f['school'])->create();
    $studentA = Student::factory()->for($f['school'])->create();
    $guardianB = Guardian::factory()->for($f['school'])->create();
    $studentB = Student::factory()->for($f['school'])->create();
    $slot = Carbon::parse('2027-03-01 16:00');

    $first = app(BookConsultationSlotAction::class)->execute($window->id, $guardianA->id, $studentA->id, $slot);
    expect($first->status)->toBe('booked');

    try {
        app(BookConsultationSlotAction::class)->execute($window->id, $guardianB->id, $studentB->id, $slot);
        expect(false)->toBeTrue('Expected ConsultationSlotAlreadyBookedException to be thrown.');
    } catch (ConsultationSlotAlreadyBookedException $e) {
        expect($e->details()['next_available'])->toContain('16:10');
    }

    expect(ConsultationBooking::where('window_id', $window->id)->where('status', 'booked')->count())->toBe(1);
});

it('cancelling a meeting releases its consultation slot back to the booking pool (BR-COM-07-009)', function (): void {
    $f = com07Fixture();
    NotificationKeyTemplateForCom07('comms.meeting_cancelled');

    $provider = app(RegisterMeetingProviderAction::class)->execute($f['school']->id, 'zoom', 'secret');
    $window = app(CreateConsultationWindowAction::class)->execute(
        schoolId: $f['school']->id, termId: $f['term']->id,
        staffId: Staff::factory()->for($f['school'])->create()->id,
        eventName: 'Consultations', availableFrom: Carbon::parse('2027-03-01 16:00'),
        availableTo: Carbon::parse('2027-03-01 18:00'), slotDurationMinutes: 10,
    );
    $guardianUser = User::factory()->create();
    $guardian = Guardian::factory()->for($f['school'])->create(['user_id' => $guardianUser->id, 'primary_phone' => '0771234567', 'email' => 'g@example.com']);
    $student = Student::factory()->for($f['school'])->create();
    $slot = Carbon::parse('2027-03-01 16:00');

    $meeting = app(ScheduleMeetingAction::class)->execute(new ScheduleMeetingData(
        schoolId: $f['school']->id, termId: $f['term']->id, meetingType: 'consultation',
        providerId: $provider->id, topic: 'Consultation', startsAt: $slot, durationMinutes: 10,
    ));
    $booking = app(BookConsultationSlotAction::class)->execute($window->id, $guardian->id, $student->id, $slot);
    $booking->update(['meeting_id' => $meeting->id]);

    app(CancelScheduledMeetingAction::class)->execute($meeting->id);

    expect($booking->fresh()->status)->toBe('cancelled')
        ->and(ScheduledMeeting::findOrFail($meeting->id)->status)->toBe('cancelled');

    // The slot is free again — a new booking at the same slot_starts_at succeeds.
    $rebooked = app(BookConsultationSlotAction::class)->execute($window->id, $guardian->id, $student->id, $slot);
    expect($rebooked->status)->toBe('booked');
});

it('purges an expired recording url but leaves an unexpired one alone (BR-COM-07-004)', function (): void {
    $f = com07Fixture();
    $provider = app(RegisterMeetingProviderAction::class)->execute($f['school']->id, 'zoom', 'secret');

    $expired = ScheduledMeeting::factory()->for($f['school'])->create([
        'provider_id' => $provider->id, 'recording_url' => 'https://example.com/rec1',
        'recording_expires_on' => now()->subDay(),
    ]);
    $current = ScheduledMeeting::factory()->for($f['school'])->create([
        'provider_id' => $provider->id, 'recording_url' => 'https://example.com/rec2',
        'recording_expires_on' => now()->addDay(),
    ]);

    $purged = app(PurgeExpiredRecordingsAction::class)->execute($f['school']->id);

    expect($purged)->toBe(1)
        ->and($expired->fresh()->recording_url)->toBeNull()
        ->and($current->fresh()->recording_url)->not->toBeNull();
});

function NotificationKeyTemplateForCom07(string $key): void
{
    app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
        key: $key, channel: 'email', body: 'Meeting cancelled: {{ meeting.starts_at }}',
    ));
}
