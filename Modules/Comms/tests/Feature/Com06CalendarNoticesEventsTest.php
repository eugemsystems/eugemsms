<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Comms\Domain\Actions\CancelEventRegistrationAction;
use Modules\Comms\Domain\Actions\CheckInEventAttendeeAction;
use Modules\Comms\Domain\Actions\ConfirmEventTicketPaymentAction;
use Modules\Comms\Domain\Actions\CreateCalendarEventAction;
use Modules\Comms\Domain\Actions\CreateEventRegistrationAction;
use Modules\Comms\Domain\Actions\EscalateUnreadUrgentNoticeAction;
use Modules\Comms\Domain\Actions\GenerateIcalFeedAction;
use Modules\Comms\Domain\Actions\GetCalendarForViewerAction;
use Modules\Comms\Domain\Actions\IssueCalendarFeedTokenAction;
use Modules\Comms\Domain\Actions\MarkNoticeReadAction;
use Modules\Comms\Domain\Actions\PostNoticeAction;
use Modules\Comms\Domain\Actions\RebuildCalendarAction;
use Modules\Comms\Domain\Actions\RegisterForEventAction;
use Modules\Comms\Domain\Actions\RegisterMessageGatewayAction;
use Modules\Comms\Domain\DataObjects\CalendarSourceDefinition;
use Modules\Comms\Domain\DataObjects\CalendarSourceRecord;
use Modules\Comms\Domain\DataObjects\PostNoticeData;
use Modules\Comms\Domain\DataObjects\RegisterForEventData;
use Modules\Comms\Domain\DataObjects\RegisterMessageGatewayData;
use Modules\Comms\Domain\Exceptions\TicketingRequiresFeeComponentException;
use Modules\Comms\Domain\Exceptions\TicketNotPaidException;
use Modules\Comms\Domain\Exceptions\TicketRequiresStudentAccountException;
use Modules\Comms\Domain\Registry\CalendarSourceRegistry;
use Modules\Comms\Models\CalendarEvent;
use Modules\Comms\Models\EventAttendee;
use Modules\Comms\Models\EventRegistration;
use Modules\Comms\Models\Notice;
use Modules\Comms\Models\NoticeRead;
use Modules\Core\Domain\Actions\Notifications\CreateNotificationTemplateAction;
use Modules\Core\Domain\DataObjects\Notifications\CreateNotificationTemplateData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\AdHocCharge;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\Receipt;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

// A manual `Carbon::setTestNow()` reset at the end of a test body never
// runs if an earlier expectation in that same test throws — leaking a
// frozen "now" into every test that runs afterward in the same process
// (order-dependent, so it can pass locally and fail in CI or a fresh
// checkout purely by luck of execution order). `afterEach` runs
// unconditionally, pass or fail.
afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @return array{school: School, user: User, year: AcademicYear, term: Term}
 */
function com06Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $user = User::factory()->create();

    return compact('school', 'user', 'year', 'term');
}

it('rebuilds calendar events from a registered source and cancels registrants when the source stops returning one (BR-COM-06-001/002/009)', function (): void {
    $f = com06Fixture();
    CalendarSourceRegistry::clear();
    $returnRecord = true;

    CalendarSourceRegistry::register(new CalendarSourceDefinition(
        moduleCode: 'TEST',
        sourceType: 'test_fixture',
        defaultAudienceScope: 'whole_school',
        syncer: function (int $schoolId) use (&$returnRecord, $f): Collection {
            if (! $returnRecord) {
                return collect();
            }

            return collect([new CalendarSourceRecord(
                sourceModuleId: 999,
                academicYearId: $f['year']->id,
                termId: $f['term']->id,
                title: 'Sports Day',
                startsAt: Carbon::parse('2027-03-10'),
            )]);
        },
    ));

    $rebuilt = app(RebuildCalendarAction::class)->execute($f['school']->id);
    expect($rebuilt)->toBe(1);

    $event = CalendarEvent::where('school_id', $f['school']->id)->where('source_type', 'test_fixture')->firstOrFail();
    expect($event->title)->toBe('Sports Day');

    $registration = EventRegistration::factory()->for($f['school'])->create(['calendar_event_id' => $event->id]);
    $guardianUser = User::factory()->create();
    $guardian = Guardian::factory()->for($f['school'])->create(['user_id' => $guardianUser->id]);
    $attendee = EventAttendee::factory()->for($f['school'])->create([
        'registration_id' => $registration->id, 'guardian_id' => $guardian->id, 'status' => 'registered',
    ]);

    $returnRecord = false;
    app(RebuildCalendarAction::class)->execute($f['school']->id);

    // The event itself survives — it carries real registration/ticketing
    // history that a hard delete would silently erase (see the action's
    // own docblock) — but every active attendee is cancelled.
    expect(CalendarEvent::where('id', $event->id)->exists())->toBeTrue()
        ->and($attendee->refresh()->status)->toBe('cancelled');
});

it('deletes a stale source-derived event outright once it has no registration history at all (BR-COM-06-009)', function (): void {
    $f = com06Fixture();
    CalendarSourceRegistry::clear();
    $returnRecord = true;

    CalendarSourceRegistry::register(new CalendarSourceDefinition(
        moduleCode: 'TEST',
        sourceType: 'test_fixture_bare',
        defaultAudienceScope: 'whole_school',
        syncer: function (int $schoolId) use (&$returnRecord, $f): Collection {
            if (! $returnRecord) {
                return collect();
            }

            return collect([new CalendarSourceRecord(
                sourceModuleId: 111, academicYearId: $f['year']->id, termId: $f['term']->id,
                title: 'Moved Fixture', startsAt: Carbon::parse('2027-04-01'),
            )]);
        },
    ));

    app(RebuildCalendarAction::class)->execute($f['school']->id);
    $event = CalendarEvent::where('school_id', $f['school']->id)->where('source_type', 'test_fixture_bare')->firstOrFail();

    $returnRecord = false;
    app(RebuildCalendarAction::class)->execute($f['school']->id);

    expect(CalendarEvent::where('id', $event->id)->exists())->toBeFalse();
});

it('filters the calendar by audience scope so a lower-grade learner never sees a higher-level-only event, while staff see everything (AC-COM-06-002)', function (): void {
    $f = com06Fixture();
    $lowerGrade = GradeLevel::factory()->for($f['school'])->create(['ordinal' => 2]);
    $higherGrade = GradeLevel::factory()->for($f['school'])->create(['ordinal' => 12]);

    $learnerUser = User::factory()->create();
    Student::factory()->for($f['school'])->create(['user_id' => $learnerUser->id, 'grade_level_id' => $lowerGrade->id]);

    $staffUser = User::factory()->create();
    Staff::factory()->for($f['school'])->create(['user_id' => $staffUser->id]);

    CalendarEvent::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'title' => 'A-Level Mock Exam',
        'audience_scope' => 'level', 'audience_scope_id' => $higherGrade->id,
        'starts_at' => now()->addDays(5),
    ]);
    CalendarEvent::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'title' => 'Whole School Assembly',
        'audience_scope' => 'whole_school', 'starts_at' => now()->addDays(5),
    ]);

    $learnerView = app(GetCalendarForViewerAction::class)
        ->execute($learnerUser, $f['school']->id, now(), now()->addDays(10));
    $staffView = app(GetCalendarForViewerAction::class)
        ->execute($staffUser, $f['school']->id, now(), now()->addDays(10));

    expect($learnerView->pluck('title'))->not->toContain('A-Level Mock Exam')
        ->and($learnerView->pluck('title'))->toContain('Whole School Assembly')
        ->and($staffView->pluck('title'))->toContain('A-Level Mock Exam', 'Whole School Assembly');
});

it('posts a notice as published or scheduled depending on its publish_at (BR-COM-06-003)', function (): void {
    $f = com06Fixture();

    $published = app(PostNoticeAction::class)->execute(new PostNoticeData(
        schoolId: $f['school']->id, title: 'Uniform Reminder', body: 'Full uniform from Monday.',
        audienceScope: 'whole_school', postedByUserId: $f['user']->id,
    ));
    $scheduled = app(PostNoticeAction::class)->execute(new PostNoticeData(
        schoolId: $f['school']->id, title: 'Term 2 Opening', body: 'Term 2 opens on the 5th.',
        audienceScope: 'whole_school', postedByUserId: $f['user']->id, publishAt: now()->addDay(),
    ));

    expect($published->status)->toBe('published')
        ->and($scheduled->status)->toBe('scheduled');
});

it('tracks read receipts only for important/urgent notices, idempotently (BR-COM-06-004)', function (): void {
    $f = com06Fixture();
    $normal = Notice::factory()->for($f['school'])->create(['priority' => 'normal', 'posted_by' => $f['user']->id]);
    $urgent = Notice::factory()->for($f['school'])->create(['priority' => 'urgent', 'posted_by' => $f['user']->id]);
    $reader = User::factory()->create();

    expect(app(MarkNoticeReadAction::class)->execute($normal->id, $reader->id))->toBeNull();

    $read = app(MarkNoticeReadAction::class)->execute($urgent->id, $reader->id);
    $again = app(MarkNoticeReadAction::class)->execute($urgent->id, $reader->id);

    expect($read)->not->toBeNull()
        ->and($read->id)->toBe($again->id)
        ->and(NoticeRead::where('notice_id', $urgent->id)->count())->toBe(1);
});

it('escalates a substantially-unread urgent notice to its poster exactly once, and not before the configured delay (BR-COM-06-005)', function (): void {
    $f = com06Fixture();
    $posterUser = $f['user'];
    Staff::factory()->for($f['school'])->create(['user_id' => $posterUser->id, 'work_email' => 'poster@example.com']);
    Guardian::factory()->for($f['school'])->create(['user_id' => User::factory()->create()->id]);
    Guardian::factory()->for($f['school'])->create(['user_id' => User::factory()->create()->id]);

    NotificationKeyTemplateFor('comms.notice_escalation');
    app(RegisterMessageGatewayAction::class)->execute(new RegisterMessageGatewayData(
        schoolId: $f['school']->id, channel: 'email', driver: 'smtp_relay', name: 'Test Email Gateway',
        credentials: 'test-key', createdByUserId: $f['user']->id,
    ));

    Carbon::setTestNow('2027-01-01 10:00:00');
    $notice = Notice::factory()->for($f['school'])->create([
        'priority' => 'urgent', 'status' => 'published', 'posted_by' => $posterUser->id,
        'publish_at' => Carbon::now(),
    ]);

    expect(app(EscalateUnreadUrgentNoticeAction::class)->execute($notice->id))->toBeFalse();

    Carbon::setTestNow('2027-01-01 11:05:00');
    $first = app(EscalateUnreadUrgentNoticeAction::class)->execute($notice->id);
    $second = app(EscalateUnreadUrgentNoticeAction::class)->execute($notice->id);

    expect($first)->toBeTrue()
        ->and($second)->toBeTrue()
        ->and(Notification::where('notification_key', 'comms.notice_escalation')->where('status', 'sent')->count())->toBe(1);
});

/**
 * @return array{registration: EventRegistration, feeComponent: FeeComponent, event: CalendarEvent}
 */
function com06TicketedEventFixture(array $f, ?int $capacity = null): array
{
    $event = app(CreateCalendarEventAction::class)->execute(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, title: 'Founders Day Fete',
        startsAt: Carbon::parse('2027-05-01'), termId: $f['term']->id,
    );
    $feeComponent = FeeComponent::factory()->for($f['school'])->create();
    $registration = app(CreateEventRegistrationAction::class)->execute(
        schoolId: $f['school']->id, calendarEventId: $event->id, capacity: $capacity,
        requiresTicket: true, ticketPriceMinor: 500, ticketCurrency: 'USD', feeComponentId: $feeComponent->id,
    );

    return compact('registration', 'feeComponent', 'event');
}

it('requires a fee_component_id to create a ticketed registration (BR-COM-06-006)', function (): void {
    $f = com06Fixture();
    $event = app(CreateCalendarEventAction::class)->execute(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, title: 'Gala', startsAt: Carbon::parse('2027-06-01'),
    );

    expect(fn () => app(CreateEventRegistrationAction::class)->execute(
        schoolId: $f['school']->id, calendarEventId: $event->id, requiresTicket: true, ticketPriceMinor: 500,
    ))->toThrow(TicketingRequiresFeeComponentException::class);
});

it('raises a real ad hoc charge at ticketed registration and refuses check-in until payment is confirmed (AC-COM-06-003)', function (): void {
    $f = com06Fixture();
    ['registration' => $registration] = com06TicketedEventFixture($f);
    $student = Student::factory()->for($f['school'])->create();

    $result = app(RegisterForEventAction::class)->execute(new RegisterForEventData(
        schoolId: $f['school']->id, registrationId: $registration->id, attendeeType: 'guardian',
        attendeeName: 'Jane Doe', studentId: $student->id, raisedByUserId: $f['user']->id,
    ));

    expect($result->waitlisted)->toBeFalse()
        ->and($result->attendee->ad_hoc_charge_id)->not->toBeNull();

    $charge = AdHocCharge::findOrFail($result->attendee->ad_hoc_charge_id);
    expect($charge->status)->toBe('pending')
        ->and($charge->amount_minor)->toBe(500);

    expect(fn () => app(CheckInEventAttendeeAction::class)->execute($result->attendee->id))
        ->toThrow(TicketNotPaidException::class);

    $receipt = Receipt::factory()->create(['school_id' => $f['school']->id]);
    $paid = app(ConfirmEventTicketPaymentAction::class)->execute($result->attendee->id, $receipt->id);
    expect($paid->status)->toBe('paid');

    $checkedIn = app(CheckInEventAttendeeAction::class)->execute($result->attendee->id);
    expect($checkedIn->status)->toBe('checked_in')
        ->and($checkedIn->checked_in_at)->not->toBeNull();
});

it('refuses to raise a ticket charge for an attendee with no linked student fee account', function (): void {
    $f = com06Fixture();
    ['registration' => $registration] = com06TicketedEventFixture($f);

    expect(fn () => app(RegisterForEventAction::class)->execute(new RegisterForEventData(
        schoolId: $f['school']->id, registrationId: $registration->id, attendeeType: 'external',
        attendeeName: 'Walk-in Guest', raisedByUserId: $f['user']->id,
    )))->toThrow(TicketRequiresStudentAccountException::class);
});

it('offers a waitlist instead of a silent failure once an event is full, and promotes the top entry when a seat frees up (AC-COM-06-004, BR-COM-06-007)', function (): void {
    $f = com06Fixture();
    ['registration' => $registration] = com06TicketedEventFixture($f, capacity: 1);
    $seatedStudent = Student::factory()->for($f['school'])->create();

    $seated = app(RegisterForEventAction::class)->execute(new RegisterForEventData(
        schoolId: $f['school']->id, registrationId: $registration->id, attendeeType: 'guardian', attendeeName: 'First In',
        studentId: $seatedStudent->id, raisedByUserId: $f['user']->id,
    ));
    expect($seated->waitlisted)->toBeFalse();

    $waitlistedUser = User::factory()->create();
    $waitlistedGuardian = Guardian::factory()->for($f['school'])->create(['user_id' => $waitlistedUser->id]);
    $waitlisted = app(RegisterForEventAction::class)->execute(new RegisterForEventData(
        schoolId: $f['school']->id, registrationId: $registration->id, attendeeType: 'guardian',
        attendeeName: 'Second In', guardianId: $waitlistedGuardian->id,
    ));

    expect($waitlisted->waitlisted)->toBeTrue()
        ->and($waitlisted->waitlistPosition)->toBe(1);

    app(CancelEventRegistrationAction::class)->execute($seated->attendee->id);

    $promoted = EventAttendee::findOrFail($waitlisted->attendee->id);
    expect($promoted->status)->toBe('registered')
        ->and($promoted->waitlist_position)->toBeNull()
        ->and($registration->fresh()->registered_count)->toBe(1);
});

it('the public iCal feed respects the token\'s own audience scope and never includes a staff-only event (AC-COM-06-005)', function (): void {
    $f = com06Fixture();
    $section = GradeLevel::factory()->for($f['school'])->create(['ordinal' => 3]);

    CalendarEvent::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'title' => 'Public Whole School Event',
        'audience_scope' => 'whole_school', 'is_public' => true, 'starts_at' => now()->addDay(),
    ]);
    CalendarEvent::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'title' => 'Staff Meeting',
        'audience_scope' => 'staff', 'is_public' => true, 'starts_at' => now()->addDay(),
    ]);
    CalendarEvent::factory()->for($f['school'])->create([
        'academic_year_id' => $f['year']->id, 'title' => 'Level-Only Event', 'is_public' => true,
        'audience_scope' => 'level', 'audience_scope_id' => $section->id, 'starts_at' => now()->addDay(),
    ]);

    $token = app(IssueCalendarFeedTokenAction::class)->execute($f['school']->id, $f['user']->id, 'level', $section->id);
    $ics = app(GenerateIcalFeedAction::class)->execute($token->token);

    expect($ics)->toContain('Public Whole School Event')
        ->toContain('Level-Only Event')
        ->not->toContain('Staff Meeting');
});

/**
 * Registers a minimal SMS template for a notification key so
 * `DispatchNotificationAction` has something real to render, mirroring
 * `com02Fixture`'s own fixture helper.
 */
function NotificationKeyTemplateFor(string $key): void
{
    app(CreateNotificationTemplateAction::class)->execute(new CreateNotificationTemplateData(
        key: $key, channel: 'email', body: 'Notice: {{ notice.title }} — {{ unread_percent }}% unread.',
    ));
}
