<?php

use App\Models\User;
use Modules\Academic\Domain\Actions\AddObservationTeacherCommentAction;
use Modules\Academic\Domain\Actions\ApproveSchemeOfWorkAction;
use Modules\Academic\Domain\Actions\ComputeCoverageStatusAction;
use Modules\Academic\Domain\Actions\CreateLessonPlanAction;
use Modules\Academic\Domain\Actions\CreateObservationRubricAction;
use Modules\Academic\Domain\Actions\CreateSchemeOfWorkAction;
use Modules\Academic\Domain\Actions\RecordDepartmentMeetingAction;
use Modules\Academic\Domain\Actions\RecordLessonObservationAction;
use Modules\Academic\Domain\Actions\RecordTopicDeliveryAction;
use Modules\Academic\Domain\Actions\SubmitLessonPlanAction;
use Modules\Academic\Domain\Actions\SubmitSchemeOfWorkAction;
use Modules\Academic\Domain\Actions\UpdateActionItemStatusAction;
use Modules\Academic\Domain\DataObjects\AddObservationTeacherCommentData;
use Modules\Academic\Domain\DataObjects\ApproveSchemeOfWorkData;
use Modules\Academic\Domain\DataObjects\ComputeCoverageStatusData;
use Modules\Academic\Domain\DataObjects\CreateLessonPlanData;
use Modules\Academic\Domain\DataObjects\CreateObservationRubricData;
use Modules\Academic\Domain\DataObjects\CreateSchemeOfWorkData;
use Modules\Academic\Domain\DataObjects\RecordDepartmentMeetingData;
use Modules\Academic\Domain\DataObjects\RecordLessonObservationData;
use Modules\Academic\Domain\DataObjects\RecordTopicDeliveryData;
use Modules\Academic\Domain\DataObjects\SubmitLessonPlanData;
use Modules\Academic\Domain\DataObjects\SubmitSchemeOfWorkData;
use Modules\Academic\Domain\DataObjects\UpdateActionItemStatusData;
use Modules\Academic\Domain\Exceptions\LessonDateNotOnTimetableSlotException;
use Modules\Academic\Domain\Exceptions\SchemeOfWorkNotApprovedException;
use Modules\Academic\Domain\Support\CycleDayResolver;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\LessonObservation;
use Modules\Academic\Models\PeriodStructure;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\CalendarHoliday;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Department;
use Modules\People\Models\Staff;

/**
 * @return array{school: School, year: AcademicYear, term: Term, subject: Subject, teacher: Staff, hod: User}
 */
function aca11Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $framework = CurriculumFramework::factory()->for($school)->create();
    $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
    $teacher = Staff::factory()->for($school)->create();
    $hod = User::factory()->create();

    return ['school' => $school, 'year' => $year, 'term' => $term, 'subject' => $subject, 'teacher' => $teacher, 'hod' => $hod];
}

/**
 * @param  array<string, mixed>  $f
 */
function aca11GradeLevel(array $f): GradeLevel
{
    return GradeLevel::factory()->for($f['school'])->create();
}

it('blocks a linked lesson plan from submission until its scheme of work is HOD-approved (AC-ACA-11-001)', function (): void {
    $f = aca11Fixture();
    $gradeLevel = aca11GradeLevel($f);

    $scheme = app(CreateSchemeOfWorkAction::class)->execute(new CreateSchemeOfWorkData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        subjectId: $f['subject']->id, gradeLevelId: $gradeLevel->id, teacherStaffId: $f['teacher']->id,
        plannedTopics: [['week' => 1, 'topic' => 'Intro'], ['week' => 2, 'topic' => 'Core']],
    ));
    app(SubmitSchemeOfWorkAction::class)->execute(new SubmitSchemeOfWorkData(schemeOfWorkId: $scheme->id));

    $plan = app(CreateLessonPlanAction::class)->execute(new CreateLessonPlanData(
        schoolId: $f['school']->id, teacherStaffId: $f['teacher']->id, lessonDate: now(),
        topic: 'Intro lesson', schemeOfWorkId: $scheme->id,
    ));

    app(SubmitLessonPlanAction::class)->execute(new SubmitLessonPlanData(lessonPlanId: $plan->id));
})->throws(SchemeOfWorkNotApprovedException::class);

it('allows a linked lesson plan to be submitted once its scheme of work is approved', function (): void {
    $f = aca11Fixture();
    $gradeLevel = aca11GradeLevel($f);

    $scheme = app(CreateSchemeOfWorkAction::class)->execute(new CreateSchemeOfWorkData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        subjectId: $f['subject']->id, gradeLevelId: $gradeLevel->id, teacherStaffId: $f['teacher']->id,
        plannedTopics: [['week' => 1, 'topic' => 'Intro'], ['week' => 2, 'topic' => 'Core']],
    ));
    app(SubmitSchemeOfWorkAction::class)->execute(new SubmitSchemeOfWorkData(schemeOfWorkId: $scheme->id));
    app(ApproveSchemeOfWorkAction::class)->execute(new ApproveSchemeOfWorkData(schemeOfWorkId: $scheme->id, reviewedByUserId: $f['hod']->id));

    $plan = app(CreateLessonPlanAction::class)->execute(new CreateLessonPlanData(
        schoolId: $f['school']->id, teacherStaffId: $f['teacher']->id, lessonDate: now(),
        topic: 'Intro lesson', schemeOfWorkId: $scheme->id,
    ));

    $submitted = app(SubmitLessonPlanAction::class)->execute(new SubmitLessonPlanData(lessonPlanId: $plan->id));

    expect($submitted->status)->toBe('submitted');
});

it('shows a topic delivered later than planned as behind-schedule, with the variance visible (AC-ACA-11-002)', function (): void {
    $f = aca11Fixture();
    $gradeLevel = aca11GradeLevel($f);

    $scheme = app(CreateSchemeOfWorkAction::class)->execute(new CreateSchemeOfWorkData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        subjectId: $f['subject']->id, gradeLevelId: $gradeLevel->id, teacherStaffId: $f['teacher']->id,
        plannedTopics: [
            ['week' => 1, 'topic' => 'Topic 1'], ['week' => 2, 'topic' => 'Topic 2'],
            ['week' => 3, 'topic' => 'Topic 3'], ['week' => 5, 'topic' => 'Topic 4'],
        ],
    ));

    // Topic 4 planned for week 5, actually delivered in week 7 (day 43, since week 7 starts on day 42).
    app(RecordTopicDeliveryAction::class)->execute(new RecordTopicDeliveryData(
        schemeOfWorkId: $scheme->id, plannedTopicIndex: 3,
        actualDeliveredOn: $f['term']->starts_on->copy()->addDays(43), varianceNote: 'delayed — public holiday',
    ));

    $coverage = app(ComputeCoverageStatusAction::class)->execute(new ComputeCoverageStatusData(schemeOfWorkId: $scheme->id));

    expect($coverage[3]['status'])->toBe('behind')
        ->and($coverage[3]['deliveredWeek'])->toBe(7)
        ->and($coverage[3]['varianceNote'])->toBe('delayed — public holiday');
});

it('lets the observed teacher add comments but never alter the observer\'s scores (AC-ACA-11-003)', function (): void {
    $f = aca11Fixture();
    $observer = Staff::factory()->for($f['school'])->create();
    $rubric = app(CreateObservationRubricAction::class)->execute(new CreateObservationRubricData(
        schoolId: $f['school']->id, name: 'Standard Rubric',
        criteria: [['criterion' => 'Planning', 'descriptor_levels' => ['emerging', 'proficient']]],
    ));

    $observation = app(RecordLessonObservationAction::class)->execute(new RecordLessonObservationData(
        schoolId: $f['school']->id, termId: $f['term']->id, observedStaffId: $f['teacher']->id,
        observerStaffId: $observer->id, rubricId: $rubric->id, observedAt: now(),
        scores: ['Planning' => 'proficient'], overallRating: 'good',
    ));

    $commented = app(AddObservationTeacherCommentAction::class)->execute(new AddObservationTeacherCommentData(
        observationId: $observation->id, commentingStaffId: $f['teacher']->id, comments: 'I agree with this assessment.',
    ));

    expect($commented->teacher_comments)->toBe('I agree with this assessment.')
        ->and($commented->teacher_acknowledged)->toBeTrue()
        ->and($commented->scores)->toBe(['Planning' => 'proficient']);

    app(AddObservationTeacherCommentAction::class)->execute(new AddObservationTeacherCommentData(
        observationId: $observation->id, commentingStaffId: $observer->id, comments: 'Trying to edit as the observer.',
    ));
})->throws(DomainException::class);

it('links a follow-up observation to the one it follows, showing the trajectory across both (AC-ACA-11-004)', function (): void {
    $f = aca11Fixture();
    $observer = Staff::factory()->for($f['school'])->create();
    $rubric = app(CreateObservationRubricAction::class)->execute(new CreateObservationRubricData(
        schoolId: $f['school']->id, name: 'Standard Rubric',
        criteria: [['criterion' => 'Planning', 'descriptor_levels' => ['emerging', 'proficient']]],
    ));

    $first = app(RecordLessonObservationAction::class)->execute(new RecordLessonObservationData(
        schoolId: $f['school']->id, termId: $f['term']->id, observedStaffId: $f['teacher']->id,
        observerStaffId: $observer->id, rubricId: $rubric->id, observedAt: now()->subMonth(),
        scores: ['Planning' => 'emerging'], overallRating: 'needs_improvement',
    ));

    $followUp = app(RecordLessonObservationAction::class)->execute(new RecordLessonObservationData(
        schoolId: $f['school']->id, termId: $f['term']->id, observedStaffId: $f['teacher']->id,
        observerStaffId: $observer->id, rubricId: $rubric->id, observedAt: now(),
        scores: ['Planning' => 'proficient'], overallRating: 'good', followUpObservationId: $first->id,
    ));

    expect($followUp->follow_up_observation_id)->toBe($first->id);

    $history = LessonObservation::where('observed_staff_id', $f['teacher']->id)
        ->orderBy('observed_at')
        ->get();

    expect($history)->toHaveCount(2)
        ->and($history->last()->follow_up_observation_id)->toBe($first->id);
});

it('makes a department meeting action item independently trackable without reopening the minutes (AC-ACA-11-005)', function (): void {
    $f = aca11Fixture();
    $department = Department::factory()->for($f['school'])->create();

    $meeting = app(RecordDepartmentMeetingAction::class)->execute(new RecordDepartmentMeetingData(
        schoolId: $f['school']->id, departmentId: $department->id, meetingDate: now(),
        attendeeStaffIds: [$f['teacher']->id], minutes: 'Discussed term planning and coverage.',
        chairedByStaffId: $f['teacher']->id,
        actionItems: [
            ['action' => 'Submit revised scheme of work', 'owner' => $f['teacher']->id, 'due_date' => now()->addWeek()->toDateString()],
        ],
    ));

    expect($meeting->action_items[0]['status'])->toBe('open');

    $updated = app(UpdateActionItemStatusAction::class)->execute(new UpdateActionItemStatusData(
        meetingId: $meeting->id, actionItemIndex: 0, status: 'done',
    ));

    expect($updated->action_items[0]['status'])->toBe('done')
        ->and($updated->minutes)->toBe('Discussed term planning and coverage.');
});

it('validates a linked lesson plan\'s date against its timetable slot\'s own scheduled cycle day (BR-ACA-11-003)', function (): void {
    $slot = TimetableSlot::factory()->create(['cycle_day' => 1]);
    SchoolContext::set(School::findOrFail($slot->school_id));
    $timetable = Timetable::findOrFail($slot->timetable_id);
    $term = Term::findOrFail($timetable->term_id);
    $structure = PeriodStructure::findOrFail($timetable->structure_id);

    $resolver = app(CycleDayResolver::class);
    $holidays = CalendarHoliday::query()->where('academic_year_id', $term->academic_year_id)->get();

    $validDate = null;
    $invalidDate = null;
    $cursor = $term->starts_on->copy();

    while ($cursor->lte($term->ends_on) && ($validDate === null || $invalidDate === null)) {
        $cycleDay = $resolver->cycleDayFor($cursor, $term, $holidays, $structure->cycle_days);

        if ($cycleDay === 1 && $validDate === null) {
            $validDate = $cursor->copy();
        } elseif ($cycleDay !== null && $cycleDay !== 1 && $invalidDate === null) {
            $invalidDate = $cursor->copy();
        }

        $cursor = $cursor->addDay();
    }

    expect($validDate)->not->toBeNull()->and($invalidDate)->not->toBeNull();

    $plan = app(CreateLessonPlanAction::class)->execute(new CreateLessonPlanData(
        schoolId: $slot->school_id, teacherStaffId: $slot->staff_id, lessonDate: $validDate,
        topic: 'On-slot lesson', timetableSlotId: $slot->id,
    ));

    expect($plan->timetable_slot_id)->toBe($slot->id);

    app(CreateLessonPlanAction::class)->execute(new CreateLessonPlanData(
        schoolId: $slot->school_id, teacherStaffId: $slot->staff_id, lessonDate: $invalidDate,
        topic: 'Off-slot lesson', timetableSlotId: $slot->id,
    ));
})->throws(LessonDateNotOnTimetableSlotException::class);
