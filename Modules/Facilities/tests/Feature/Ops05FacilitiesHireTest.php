<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Academic\Domain\Actions\CreatePeriodStructureAction;
use Modules\Academic\Domain\Actions\CreateTimetableSlotAction;
use Modules\Academic\Domain\Actions\CreateVenueAction;
use Modules\Academic\Domain\Actions\PublishTimetableAction;
use Modules\Academic\Domain\DataObjects\CreatePeriodStructureData;
use Modules\Academic\Domain\DataObjects\CreateTimetableSlotData;
use Modules\Academic\Domain\DataObjects\CreateVenueData;
use Modules\Academic\Domain\DataObjects\PeriodSlotInput;
use Modules\Academic\Domain\DataObjects\PublishTimetableData;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\Timetable;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Facilities\Domain\Actions\ApproveExternalHireAction;
use Modules\Facilities\Domain\Actions\AssessDamageAndRefundDepositAction;
use Modules\Facilities\Domain\Actions\CancelBookingAction;
use Modules\Facilities\Domain\Actions\CompleteBookingAction;
use Modules\Facilities\Domain\Actions\ComputeUtilisationReportAction;
use Modules\Facilities\Domain\Actions\ConfirmBookingAction;
use Modules\Facilities\Domain\Actions\CreateBookableResourceAction;
use Modules\Facilities\Domain\Actions\ExpandRecurringBookingAction;
use Modules\Facilities\Domain\Actions\RecordHireDepositAction;
use Modules\Facilities\Domain\Actions\RequestBookingAction;
use Modules\Facilities\Domain\DataObjects\AssessDamageAndRefundDepositData;
use Modules\Facilities\Domain\DataObjects\ConfirmBookingData;
use Modules\Facilities\Domain\DataObjects\CreateBookableResourceData;
use Modules\Facilities\Domain\DataObjects\RecordHireDepositData;
use Modules\Facilities\Domain\DataObjects\RequestBookingData;
use Modules\Facilities\Domain\Exceptions\ResourceNotAvailableException;
use Modules\Facilities\Models\BookableResource;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\Journal;
use Modules\Operations\Models\WorkOrder;
use Modules\People\Models\Staff;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User, user2: User, costCentre: CostCentre, cashAccount: Account, depositsAccount: Account, damageIncomeAccount: Account}
 */
function ops05Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $termStart = Carbon::parse('2026-09-07'); // a Monday.
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'is_current' => true, 'financial_state' => 'open', 'starts_on' => $termStart, 'ends_on' => $termStart->copy()->addDays(90),
    ]);
    $user = User::factory()->create();
    $user2 = User::factory()->create();

    foreach (['journal', 'resource_booking', 'work_order'] as $type) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $type, pattern: strtoupper(substr($type, 0, 3)).'/{SEQ:5}', termId: $term->id,
        ));
    }

    $costCentre = CostCentre::factory()->for($school)->create();
    $cashAccount = Account::factory()->for($school)->create(['code' => 'CASH-FAC']);
    $depositsAccount = Account::factory()->for($school)->create(['code' => 'DEPOSITS-HELD']);
    $damageIncomeAccount = Account::factory()->for($school)->create(['code' => 'DAMAGE-RECOVERY']);

    return compact('school', 'year', 'term', 'user', 'user2', 'costCentre', 'cashAccount', 'depositsAccount', 'damageIncomeAccount');
}

/**
 * @param  array<string, mixed>  $f
 */
function ops05Resource(array $f, ?int $venueId = null): BookableResource
{
    return app(CreateBookableResourceAction::class)->execute(new CreateBookableResourceData(
        schoolId: $f['school']->id, code: 'HALL-'.fake()->unique()->numberBetween(1, 9999), name: 'Main Hall',
        resourceType: 'hall', costCentreId: $f['costCentre']->id, venueId: $venueId,
        isExternallyHireable: true, requiresSetupMinutes: 90, requiresCleaningMinutes: 60,
    ));
}

it('refuses an external hire that clashes with the published timetable, naming it (BR-OPS-05-001/AC-OPS-05-001)', function (): void {
    $f = ops05Fixture();
    $venue = app(CreateVenueAction::class)->execute(new CreateVenueData(
        schoolId: $f['school']->id, code: 'V1', name: 'Hall Venue', venueType: 'hall', capacity: 300,
    ));
    $resource = ops05Resource($f, $venue->id);

    $structure = app(CreatePeriodStructureAction::class)->execute(new CreatePeriodStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Structure', cycleType: 'weekly',
        cycleDays: 5, dayLabels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        slots: [new PeriodSlotInput(cycleDay: 1, periodNumber: 1, label: 'Period 1', slotType: 'teaching', startsAt: '10:00', endsAt: '11:00', durationMinutes: 60)],
    ));
    $periodSlot = $structure->slots()->where('cycle_day', 1)->where('period_number', 1)->first();

    $timetable = Timetable::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'structure_id' => $structure->id, 'created_by' => $f['user']->id,
        'effective_from' => $f['term']->starts_on->toDateString(),
    ]);
    $staff = Staff::factory()->for($f['school'])->create();
    $subject = Subject::factory()->for($f['school'])->create();

    app(CreateTimetableSlotAction::class)->execute(new CreateTimetableSlotData(
        timetableId: $timetable->id, termId: $f['term']->id, periodSlotId: $periodSlot->id,
        cycleDay: 1, periodNumber: 1, subjectId: $subject->id, staffId: $staff->id, venueId: $venue->id,
    ));
    app(PublishTimetableAction::class)->execute(new PublishTimetableData(timetableId: $timetable->id, publishedByUserId: $f['user']->id));

    // Term starts on a Monday with no holidays, so starts_on itself is cycle day 1.
    $lessonDate = $f['term']->starts_on->copy();

    expect(fn () => app(RequestBookingAction::class)->execute(new RequestBookingData(
        schoolId: $f['school']->id, termId: $f['term']->id, resourceId: $resource->id, bookingType: 'external',
        purpose: 'Community fundraiser', startsAt: $lessonDate->copy()->setTime(9, 0), endsAt: $lessonDate->copy()->setTime(12, 0),
        requestedByUserId: $f['user']->id, hirerName: 'Local Church',
    )))->toThrow(ResourceNotAvailableException::class);
});

it('blocks a booking that falls within another booking\'s own setup/cleaning buffer (BR-OPS-05-002/AC-OPS-05-002)', function (): void {
    $f = ops05Fixture();
    $resource = ops05Resource($f);

    $lessonDate = $f['term']->starts_on->copy()->addDays(2);

    app(RequestBookingAction::class)->execute(new RequestBookingData(
        schoolId: $f['school']->id, termId: $f['term']->id, resourceId: $resource->id, bookingType: 'internal',
        purpose: 'Staff briefing', startsAt: $lessonDate->copy()->setTime(14, 0), endsAt: $lessonDate->copy()->setTime(16, 0),
        requestedByUserId: $f['user']->id,
    ));

    // A hall needing 90 minutes setup booked for 14:00 is unavailable from
    // 12:30 — a booking ending at 12:45 clashes with that buffer.
    expect(fn () => app(RequestBookingAction::class)->execute(new RequestBookingData(
        schoolId: $f['school']->id, termId: $f['term']->id, resourceId: $resource->id, bookingType: 'internal',
        purpose: 'Earlier meeting', startsAt: $lessonDate->copy()->setTime(11, 0), endsAt: $lessonDate->copy()->setTime(12, 45),
        requestedByUserId: $f['user']->id,
    )))->toThrow(ResourceNotAvailableException::class);
});

it('requires approval, a contract and a deposit before an external hire confirms, posting the deposit to a liability not income (BR-OPS-05-003/005/AC-OPS-05-003)', function (): void {
    $f = ops05Fixture();
    $resource = ops05Resource($f);
    $lessonDate = $f['term']->starts_on->copy()->addDays(3);

    $booking = app(RequestBookingAction::class)->execute(new RequestBookingData(
        schoolId: $f['school']->id, termId: $f['term']->id, resourceId: $resource->id, bookingType: 'external',
        purpose: 'Wedding reception', startsAt: $lessonDate->copy()->setTime(14, 0), endsAt: $lessonDate->copy()->setTime(20, 0),
        requestedByUserId: $f['user']->id, hirerName: 'Jane Doe', hireAmountMinor: 50000,
    ));
    expect($booking->status)->toBe('requested');

    expect(fn () => app(ConfirmBookingAction::class)->execute($booking->id, new ConfirmBookingData(
        academicYearId: $f['year']->id, confirmedByUserId: $f['user2']->id,
    )))->toThrow(InvalidStateTransitionException::class);

    $approved = app(ApproveExternalHireAction::class)->execute($booking->id, $f['user2']->id);
    expect($approved->status)->toBe('approved');

    expect(fn () => app(ConfirmBookingAction::class)->execute($booking->id, new ConfirmBookingData(
        academicYearId: $f['year']->id, confirmedByUserId: $f['user2']->id, contractFileId: 1,
    )))->toThrow(ValidationException::class);

    app(RecordHireDepositAction::class)->execute($booking->id, new RecordHireDepositData(
        academicYearId: $f['year']->id, termId: $f['term']->id, amountMinor: 30000, currency: 'USD',
        cashAccountId: $f['cashAccount']->id, depositsHeldLiabilityAccountId: $f['depositsAccount']->id,
        performedByUserId: $f['user']->id,
    ));

    $depositJournal = Journal::where('school_id', $f['school']->id)->where('journal_type', 'HIRE_DEPOSIT_RECEIVED')->first();
    expect($depositJournal->lines->firstWhere('direction', 'CR')->account_id)->toBe($f['depositsAccount']->id);

    $confirmed = app(ConfirmBookingAction::class)->execute($booking->id, new ConfirmBookingData(
        academicYearId: $f['year']->id, confirmedByUserId: $f['user2']->id, contractFileId: 1,
    ));

    expect($confirmed->status)->toBe('confirmed')
        ->and($confirmed->setup_work_order_id)->not->toBeNull()
        ->and($confirmed->cleanup_work_order_id)->not->toBeNull();

    $setupOrder = WorkOrder::find($confirmed->setup_work_order_id);
    expect($setupOrder->cost_centre_id)->toBe($f['costCentre']->id);
});

it('deducts assessed damage from the deposit and refunds the balance (BR-OPS-05-006/AC-OPS-05-003)', function (): void {
    $f = ops05Fixture();
    $resource = ops05Resource($f);
    $lessonDate = $f['term']->starts_on->copy()->addDays(4);

    $booking = app(RequestBookingAction::class)->execute(new RequestBookingData(
        schoolId: $f['school']->id, termId: $f['term']->id, resourceId: $resource->id, bookingType: 'external',
        purpose: 'Party', startsAt: $lessonDate->copy()->setTime(14, 0), endsAt: $lessonDate->copy()->setTime(20, 0),
        requestedByUserId: $f['user']->id, hirerName: 'Jane Doe',
    ));
    app(ApproveExternalHireAction::class)->execute($booking->id, $f['user2']->id);
    app(RecordHireDepositAction::class)->execute($booking->id, new RecordHireDepositData(
        academicYearId: $f['year']->id, termId: $f['term']->id, amountMinor: 30000, currency: 'USD',
        cashAccountId: $f['cashAccount']->id, depositsHeldLiabilityAccountId: $f['depositsAccount']->id,
        performedByUserId: $f['user']->id,
    ));
    app(ConfirmBookingAction::class)->execute($booking->id, new ConfirmBookingData(
        academicYearId: $f['year']->id, confirmedByUserId: $f['user2']->id, contractFileId: 1,
    ));
    app(CompleteBookingAction::class)->execute($booking->id, 'Some chairs damaged.');

    expect(fn () => app(AssessDamageAndRefundDepositAction::class)->execute($booking->id, new AssessDamageAndRefundDepositData(
        academicYearId: $f['year']->id, termId: $f['term']->id, currency: 'USD',
        depositsHeldLiabilityAccountId: $f['depositsAccount']->id, cashAccountId: $f['cashAccount']->id,
        performedByUserId: $f['user2']->id, damageDeductedMinor: 5000,
    )))->toThrow(ValidationException::class);

    $refunded = app(AssessDamageAndRefundDepositAction::class)->execute($booking->id, new AssessDamageAndRefundDepositData(
        academicYearId: $f['year']->id, termId: $f['term']->id, currency: 'USD',
        depositsHeldLiabilityAccountId: $f['depositsAccount']->id, cashAccountId: $f['cashAccount']->id,
        performedByUserId: $f['user2']->id, damageDeductedMinor: 5000,
        damageRecoveryIncomeAccountId: $f['damageIncomeAccount']->id, damageAssessmentNote: 'Two chairs broken.',
    ));

    expect($refunded->damage_deducted_minor)->toBe(5000)
        ->and($refunded->deposit_refunded)->toBeTrue();

    $refundJournal = Journal::where('school_id', $f['school']->id)->where('journal_type', 'HIRE_DEPOSIT_REFUND')->first();
    expect($refundJournal->lines->firstWhere('direction', 'CR')->account_id)->toBe($f['cashAccount']->id)
        ->and($refundJournal->lines->where('direction', 'CR')->sum('amount_minor'))->toBe(30000);
});

it('expands a recurring booking into independently cancellable instances (BR-OPS-05-008)', function (): void {
    $f = ops05Fixture();
    $resource = ops05Resource($f);
    $lessonDate = $f['term']->starts_on->copy()->addDays(10);

    $parent = app(RequestBookingAction::class)->execute(new RequestBookingData(
        schoolId: $f['school']->id, termId: $f['term']->id, resourceId: $resource->id, bookingType: 'internal',
        purpose: 'Weekly club meeting', startsAt: $lessonDate->copy()->setTime(15, 0), endsAt: $lessonDate->copy()->setTime(16, 0),
        requestedByUserId: $f['user']->id,
    ));

    $instances = app(ExpandRecurringBookingAction::class)->execute($parent->id, 'weekly', 3, $f['user']->id);

    expect($instances)->toHaveCount(3);
    $second = $instances->get(1);
    expect($second->parent_booking_id)->toBe($parent->id);

    $cancelled = app(CancelBookingAction::class)->execute($second->id, 'Club cancelled for that week.');
    expect($cancelled->status)->toBe('cancelled')
        ->and($instances->get(2)->fresh()->status)->not->toBe('cancelled');
});

it('reports utilisation and hire revenue per resource per term (BR-OPS-05-009)', function (): void {
    $f = ops05Fixture();
    $resource = ops05Resource($f);
    $lessonDate = $f['term']->starts_on->copy()->addDays(5);

    $booking = app(RequestBookingAction::class)->execute(new RequestBookingData(
        schoolId: $f['school']->id, termId: $f['term']->id, resourceId: $resource->id, bookingType: 'external',
        purpose: 'Conference', startsAt: $lessonDate->copy()->setTime(9, 0), endsAt: $lessonDate->copy()->setTime(17, 0),
        requestedByUserId: $f['user']->id, hirerName: 'Local Business', hireAmountMinor: 80000,
    ));
    app(ApproveExternalHireAction::class)->execute($booking->id, $f['user2']->id);
    app(RecordHireDepositAction::class)->execute($booking->id, new RecordHireDepositData(
        academicYearId: $f['year']->id, termId: $f['term']->id, amountMinor: 30000, currency: 'USD',
        cashAccountId: $f['cashAccount']->id, depositsHeldLiabilityAccountId: $f['depositsAccount']->id,
        performedByUserId: $f['user']->id,
    ));
    app(ConfirmBookingAction::class)->execute($booking->id, new ConfirmBookingData(
        academicYearId: $f['year']->id, confirmedByUserId: $f['user2']->id, contractFileId: 1,
    ));

    $result = app(ComputeUtilisationReportAction::class)->execute($resource->id, $f['term']->id);

    expect($result->bookingCount)->toBe(1)
        ->and($result->bookedHours)->toBe(8.0)
        ->and($result->hireRevenueMinor)->toBe(80000);
});
