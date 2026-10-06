<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Comms\Console\Tasks\SendDueNewslettersTask;
use Modules\Comms\Domain\Actions\CreateCalendarEventAction;
use Modules\Comms\Domain\Actions\CreateEventRegistrationAction;
use Modules\Comms\Domain\Actions\CreateNewsletterAction;
use Modules\Comms\Domain\Actions\RegisterForEventAction;
use Modules\Comms\Domain\Actions\SendNewsletterAction;
use Modules\Comms\Domain\DataObjects\CreateNewsletterData;
use Modules\Comms\Domain\DataObjects\RegisterForEventData;
use Modules\Comms\Livewire\Calendar\View as CalendarView;
use Modules\Comms\Livewire\Events\CheckIn;
use Modules\Comms\Livewire\Events\Register;
use Modules\Comms\Livewire\Newsletters\Compose as NewsletterCompose;
use Modules\Comms\Livewire\Notices\Compose as NoticeCompose;
use Modules\Comms\Livewire\Notices\Index as NoticeIndex;
use Modules\Comms\Models\CalendarEvent;
use Modules\Comms\Models\EventAttendee;
use Modules\Comms\Models\EventRegistration;
use Modules\Comms\Models\Newsletter;
use Modules\Comms\Models\Notice;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Notification;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\Receipt;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * Book I COM-06 admin-UI pass. Own, distinctly-named helpers.
 *
 * @return array<string, mixed>
 */
function com06AdminFixture(): array
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
function com06AdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        [$moduleCode, $action] = explode('.', $permissionName);

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($moduleCode), 'resource' => $action, 'action' => $action],
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
function com06AdminEvent(array $f, string $title = 'Open Day'): CalendarEvent
{
    return app(CreateCalendarEventAction::class)->execute(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, title: $title,
        startsAt: Carbon::now()->addWeek(), termId: $f['term']->id,
    );
}

it('refuses every COM-06 screen to a user without its permission', function (string $component): void {
    $f = com06AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test($component, ['school' => $f['school']])->assertForbidden();
})->with([
    'calendar' => CalendarView::class,
    'notice board' => NoticeIndex::class,
    'post notice' => NoticeCompose::class,
    'newsletters' => NewsletterCompose::class,
    'register' => Register::class,
    'check-in' => CheckIn::class,
]);

it('renders every COM-06 screen for a fully-permissioned user', function (): void {
    $f = com06AdminFixture();
    $user = com06AdminUser($f, 'calendar.view', 'notices.view', 'notices.post', 'newsletters.manage', 'events.manage', 'events.checkin');

    foreach ([CalendarView::class, NoticeIndex::class, NoticeCompose::class, NewsletterCompose::class, Register::class, CheckIn::class] as $component) {
        Livewire::actingAs($user)->test($component, ['school' => $f['school']])->assertOk();
    }
});

it('adds a manual calendar event and shows it, only to a user who may manage events', function (): void {
    $f = com06AdminFixture();
    $manager = com06AdminUser($f, 'calendar.view', 'events.manage');
    $viewer = com06AdminUser($f, 'calendar.view');
    $startsAt = now()->startOfMonth()->addDays(10)->setTime(9, 0);

    Livewire::actingAs($manager)->test(CalendarView::class, ['school' => $f['school']])
        ->set('title', 'Open Day')
        ->set('startsAt', $startsAt->format('Y-m-d\TH:i'))
        ->call('createEvent')
        ->assertHasNoErrors()
        ->assertSee('Open Day');

    $event = CalendarEvent::where('school_id', $f['school']->id)->firstOrFail();
    expect($event->source_type)->toBe('manual')->and($event->academic_year_id)->toBe($f['year']->id);

    Livewire::actingAs($viewer)->test(CalendarView::class, ['school' => $f['school']])
        ->assertSee('Open Day')
        ->assertDontSee('Add an event')
        ->call('createEvent')
        ->assertForbidden();
});

it('hides a staff-only event from a non-staff viewer (AC-COM-06-002)', function (): void {
    $f = com06AdminFixture();
    $viewer = com06AdminUser($f, 'calendar.view');
    app(CreateCalendarEventAction::class)->execute(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, title: 'Staff INSET', startsAt: now()->startOfMonth()->addDays(5),
        audienceScope: 'staff', termId: $f['term']->id,
    );
    app(CreateCalendarEventAction::class)->execute(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, title: 'Whole school assembly', startsAt: now()->startOfMonth()->addDays(6),
        termId: $f['term']->id,
    );

    Livewire::actingAs($viewer)->test(CalendarView::class, ['school' => $f['school']])
        ->assertSee('Whole school assembly')
        ->assertDontSee('Staff INSET');
});

it('posts a notice to the whole school or a section, and scopes only to this school’s own targets', function (): void {
    $f = com06AdminFixture();
    $poster = com06AdminUser($f, 'notices.post');
    $section = SchoolSection::factory()->for($f['school'])->create();
    $foreignSection = SchoolSection::factory()->for(School::factory()->create())->create();
    SchoolContext::set($f['school']);

    Livewire::actingAs($poster)->test(NoticeCompose::class, ['school' => $f['school']])
        ->set('title', 'Sports day moved')
        ->set('body', 'Now on Friday.')
        ->set('priority', 'urgent')
        ->set('audienceScope', 'section')
        ->set('audienceScopeId', $section->id)
        ->call('post')
        ->assertHasNoErrors();

    $notice = Notice::where('school_id', $f['school']->id)->firstOrFail();
    expect($notice->status)->toBe('published')
        ->and($notice->audience_scope_id)->toBe($section->id)
        ->and($notice->posted_by)->toBe($poster->id);

    Livewire::actingAs($poster)->test(NoticeCompose::class, ['school' => $f['school']])
        ->set('title', 'x')->set('body', 'y')
        ->set('audienceScope', 'section')->set('audienceScopeId', $foreignSection->id)
        ->call('post')
        ->assertStatus(422);
    expect(Notice::where('school_id', $f['school']->id)->count())->toBe(1);
});

it('schedules a notice with a future publish time and validates the expiry', function (): void {
    $f = com06AdminFixture();
    $poster = com06AdminUser($f, 'notices.post');

    $component = Livewire::actingAs($poster)->test(NoticeCompose::class, ['school' => $f['school']])
        ->set('title', 'Term dates')
        ->set('body', 'See attached.')
        ->set('publishAt', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->set('expiresAt', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('post')
        ->assertHasErrors(['expiresAt']);

    $component->set('expiresAt', now()->addDays(5)->format('Y-m-d\TH:i'))->call('post')->assertHasNoErrors();

    expect(Notice::where('school_id', $f['school']->id)->firstOrFail()->status)->toBe('scheduled');
});

it('shows read counts only for important and urgent notices (BR-COM-06-004)', function (): void {
    $f = com06AdminFixture();
    $viewer = com06AdminUser($f, 'notices.view');
    Notice::factory()->create(['school_id' => $f['school']->id, 'title' => 'Plain notice', 'priority' => 'normal']);
    Notice::factory()->create(['school_id' => $f['school']->id, 'title' => 'Urgent notice', 'priority' => 'urgent']);
    SchoolContext::set($f['school']);

    $component = Livewire::actingAs($viewer)->test(NoticeIndex::class, ['school' => $f['school']]);

    $notices = $component->viewData('notices')->keyBy('title');
    expect($notices['Plain notice']->tracksReadReceipts())->toBeFalse()
        ->and($notices['Urgent notice']->tracksReadReceipts())->toBeTrue();
    $component->assertSee('Plain notice')->assertSee('Urgent notice');
});

it('saves a newsletter as a draft or scheduled, never as sent, and strips unsafe markup', function (): void {
    $f = com06AdminFixture();
    $editor = com06AdminUser($f, 'newsletters.manage');

    $component = Livewire::actingAs($editor)->test(NewsletterCompose::class, ['school' => $f['school']])
        ->set('issueNumber', '2026-T1-01')
        ->set('title', 'Term 1 news')
        ->set('contentHtml', '<p>Hello</p><script>alert(1)</script>')
        ->call('save')
        ->assertHasNoErrors();

    $draft = Newsletter::where('school_id', $f['school']->id)->firstOrFail();
    expect($draft->status)->toBe('draft')
        ->and($draft->content_html)->not->toContain('<script>')
        ->and($draft->content_html)->toContain('<p>Hello</p>')
        ->and($draft->sent_at)->toBeNull();

    $component->set('issueNumber', '2026-T1-02')->set('title', 'Term 1 news 2')->set('contentHtml', '<p>Later</p>')
        ->set('scheduledFor', now()->addWeek()->format('Y-m-d\TH:i'))->call('save')->assertHasNoErrors();
    expect(Newsletter::where('issue_number', '2026-T1-02')->firstOrFail()->status)->toBe('scheduled');

    $component->set('issueNumber', '2026-T1-01')->set('title', 'Dup')->set('contentHtml', '<p>x</p>')->call('save')->assertHasErrors(['issueNumber']);
});

it('opens registration, waitlists the overflow and promotes on cancellation (AC-COM-06-004)', function (): void {
    $f = com06AdminFixture();
    $manager = com06AdminUser($f, 'events.manage');
    $event = com06AdminEvent($f);

    $component = Livewire::actingAs($manager)->test(Register::class, ['school' => $f['school']])
        ->set('calendarEventId', $event->id)
        ->set('capacity', '1')
        ->call('createRegistration')
        ->assertHasNoErrors();

    $registration = EventRegistration::where('school_id', $f['school']->id)->firstOrFail();

    $component->set('registrationId', $registration->id)
        ->set('attendeeName', 'First Guest')->call('registerAttendee')->assertHasNoErrors()
        ->set('attendeeName', 'Second Guest')->call('registerAttendee')->assertDispatched('toast', variant: 'warning');

    $second = EventAttendee::where('attendee_name', 'Second Guest')->firstOrFail();
    expect($second->status)->toBe('waitlisted')->and($second->waitlist_position)->toBe(1);

    $first = EventAttendee::where('attendee_name', 'First Guest')->firstOrFail();
    SchoolContext::clear();
    $component->call('cancelAttendee', $first->id);

    expect($first->fresh()->status)->toBe('cancelled')
        ->and($second->fresh()->status)->toBe('registered');
});

it('refuses a duplicate registration for an event and a ticketed one without a fee component', function (): void {
    $f = com06AdminFixture();
    $manager = com06AdminUser($f, 'events.manage');
    $event = com06AdminEvent($f);
    $other = com06AdminEvent($f, 'Gala');

    $component = Livewire::actingAs($manager)->test(Register::class, ['school' => $f['school']])
        ->set('calendarEventId', $other->id)->set('requiresTicket', true)->set('ticketPrice', '5')
        ->call('createRegistration')
        ->assertHasErrors(['feeComponentId']);

    app(CreateEventRegistrationAction::class)->execute(schoolId: $f['school']->id, calendarEventId: $event->id);

    Livewire::actingAs($manager)->test(Register::class, ['school' => $f['school']])
        ->set('calendarEventId', $event->id)->call('createRegistration')->assertHasErrors(['calendarEventId']);
});

it('raises a ticket charge for a learner and asks for an admission number otherwise (BR-COM-06-006)', function (): void {
    $f = com06AdminFixture();
    $manager = com06AdminUser($f, 'events.manage');
    $event = com06AdminEvent($f);
    $component = fn () => Livewire::actingAs($manager)->test(Register::class, ['school' => $f['school']]);
    $fee = FeeComponent::factory()->for($f['school'])->create();
    $student = Student::factory()->for($f['school'])->create();

    $screen = $component()
        ->set('calendarEventId', $event->id)->set('requiresTicket', true)->set('ticketPrice', '5.00')->set('feeComponentId', $fee->id)
        ->call('createRegistration')->assertHasNoErrors();

    $registration = EventRegistration::where('school_id', $f['school']->id)->firstOrFail();
    expect($registration->ticket_price_minor)->toBe(500)->and($registration->ticket_currency)->toBe('USD');

    $screen->set('registrationId', $registration->id)->set('attendeeName', 'Parent A')
        ->call('registerAttendee')->assertHasErrors(['admissionNumber']);
    expect(EventAttendee::where('registration_id', $registration->id)->count())->toBe(0);

    $screen->set('admissionNumber', $student->admission_number)->call('registerAttendee')->assertHasNoErrors();
    expect(EventAttendee::where('registration_id', $registration->id)->firstOrFail()->ad_hoc_charge_id)->not->toBeNull();
});

it('refuses check-in until a ticket is paid, then checks in (AC-COM-06-003)', function (): void {
    $f = com06AdminFixture();
    $manager = com06AdminUser($f, 'events.manage', 'events.checkin');
    $event = com06AdminEvent($f);
    $fee = FeeComponent::factory()->for($f['school'])->create();
    $student = Student::factory()->for($f['school'])->create();
    $registration = app(CreateEventRegistrationAction::class)->execute(
        schoolId: $f['school']->id, calendarEventId: $event->id, requiresTicket: true,
        ticketPriceMinor: 500, ticketCurrency: 'USD', feeComponentId: $fee->id,
    );
    $attendee = app(RegisterForEventAction::class)->execute(new RegisterForEventData(
        schoolId: $f['school']->id, registrationId: $registration->id, attendeeType: 'guardian',
        attendeeName: 'Jane Doe', studentId: $student->id, raisedByUserId: $manager->id,
    ))->attendee;
    $receipt = Receipt::factory()->create(['school_id' => $f['school']->id]);

    $door = Livewire::actingAs($manager)->test(CheckIn::class, ['school' => $f['school']])->set('registrationId', $registration->id);
    $door->call('checkIn', $attendee->id)->assertDispatched('toast', variant: 'danger');
    expect($attendee->fresh()->status)->toBe('registered');

    $desk = Livewire::actingAs($manager)->test(Register::class, ['school' => $f['school']])->set('registrationId', $registration->id);
    $desk->set('receiptNumber', 'NO-SUCH')->call('confirmPayment', $attendee->id)->assertHasErrors(['receiptNumber']);
    $desk->set('receiptNumber', $receipt->receipt_number)->call('confirmPayment', $attendee->id)->assertHasNoErrors();
    expect($attendee->fresh()->status)->toBe('paid');

    SchoolContext::clear();
    $door->call('checkIn', $attendee->id)->assertDispatched('toast');
    expect($attendee->fresh()->status)->toBe('checked_in');
});

it('lists only seat-holding attendees at the door and filters by name', function (): void {
    $f = com06AdminFixture();
    $user = com06AdminUser($f, 'events.checkin');
    $event = com06AdminEvent($f);
    $registration = app(CreateEventRegistrationAction::class)->execute(schoolId: $f['school']->id, calendarEventId: $event->id, capacity: 1);
    foreach (['Alice Held', 'Bob Waiting'] as $name) {
        app(RegisterForEventAction::class)->execute(new RegisterForEventData(
            schoolId: $f['school']->id, registrationId: $registration->id, attendeeType: 'guardian', attendeeName: $name,
        ));
    }

    Livewire::actingAs($user)->test(CheckIn::class, ['school' => $f['school']])
        ->set('registrationId', $registration->id)
        ->assertSee('Alice Held')
        ->assertDontSee('Bob Waiting')
        ->set('search', 'zzz')
        ->assertDontSee('Alice Held');
});

it('sends a newsletter once to every distinct guardian and staff address and marks it sent (COM-06)', function (): void {
    $f = com06AdminFixture();
    $editor = com06AdminUser($f, 'newsletters.manage');
    Guardian::factory()->for($f['school'])->create(['email' => 'parent@example.com', 'status' => 'active']);
    Guardian::factory()->for($f['school'])->create(['email' => 'PARENT@example.com', 'status' => 'active']);
    Guardian::factory()->for($f['school'])->create(['email' => null, 'status' => 'active']);
    Staff::factory()->for($f['school'])->create(['work_email' => 'teacher@example.com', 'status' => 'active']);
    $newsletter = app(CreateNewsletterAction::class)->execute(new CreateNewsletterData(
        schoolId: $f['school']->id, issueNumber: '2026-T1-09', title: 'Sports day', contentHtml: '<p>See you there</p>',
    ));

    Livewire::actingAs($editor)->test(NewsletterCompose::class, ['school' => $f['school']])->call('send', $newsletter->id);

    expect($newsletter->fresh()->status)->toBe('sent')
        ->and($newsletter->fresh()->sent_at)->not->toBeNull()
        ->and(Notification::where('notification_key', 'comms.newsletter')->where('channel', 'email')->count())->toBe(2);

    Livewire::actingAs($editor)->test(NewsletterCompose::class, ['school' => $f['school']])->call('send', $newsletter->id);
    expect(Notification::where('notification_key', 'comms.newsletter')->where('channel', 'email')->count())->toBe(2);
});

it('refuses to send a newsletter aimed at a section or level', function (): void {
    $f = com06AdminFixture();
    $newsletter = app(CreateNewsletterAction::class)->execute(new CreateNewsletterData(
        schoolId: $f['school']->id, issueNumber: '2026-T1-10', title: 'Form 1 only', contentHtml: '<p>Hi</p>', audienceScope: 'level',
    ));

    expect(fn () => app(SendNewsletterAction::class)->execute($newsletter->id))->toThrow(InvalidStateTransitionException::class)
        ->and($newsletter->fresh()->status)->toBe('draft');
});

it('sends due scheduled newsletters from the scheduled task and leaves future ones', function (): void {
    $f = com06AdminFixture();
    $due = app(CreateNewsletterAction::class)->execute(new CreateNewsletterData(
        schoolId: $f['school']->id, issueNumber: '2026-T1-11', title: 'Due', contentHtml: '<p>Now</p>', scheduledFor: now()->addMinute(),
    ));
    $future = app(CreateNewsletterAction::class)->execute(new CreateNewsletterData(
        schoolId: $f['school']->id, issueNumber: '2026-T1-12', title: 'Later', contentHtml: '<p>Later</p>', scheduledFor: now()->addWeek(),
    ));
    $due->update(['scheduled_for' => now()->subMinute()]);

    expect((new SendDueNewslettersTask)->handle($f['school']))->toBe('1 newsletter(s) sent')
        ->and($due->fresh()->status)->toBe('sent')
        ->and($future->fresh()->status)->toBe('scheduled');
});
