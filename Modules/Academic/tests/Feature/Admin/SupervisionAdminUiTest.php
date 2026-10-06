<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Academic\Livewire\Supervision\Coverage;
use Modules\Academic\Livewire\Supervision\LessonPlans;
use Modules\Academic\Livewire\Supervision\Meetings;
use Modules\Academic\Livewire\Supervision\ObservationHistory;
use Modules\Academic\Livewire\Supervision\Observe;
use Modules\Academic\Livewire\Supervision\SchemeOfWork as SchemeScreen;
use Modules\Academic\Livewire\Supervision\TeacherDashboard;
use Modules\Academic\Models\DepartmentMeeting;
use Modules\Academic\Models\LessonObservation;
use Modules\Academic\Models\LessonPlan;
use Modules\Academic\Models\ObservationRubric;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Academic\Models\SyllabusCoverageRecord;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Permission;
use Modules\People\Models\Department;
use Modules\People\Models\Staff;

/**
 * Book K ACA-11 admin-UI pass. Reuses `aca11Fixture()` from
 * `Aca11SupervisionTest`, so run the module directory.
 *
 * @param  array<string, mixed>  $f
 * @return array{0: User, 1: Staff}
 */
function supAdminPerson(array $f, array $permissionNames, ?PermissionScope $scope = null): array
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);
    $staff = Staff::factory()->for($f['school'])->create(['user_id' => $user->id]);

    $grants = array_map(function (string $name) use ($scope): PermissionGrantData {
        $parts = explode('.', $name);
        $permission = Permission::firstOrCreate(['name' => $name], ['guard_name' => 'web', 'module_code' => strtoupper($parts[0]), 'resource' => $parts[1] ?? $parts[0], 'action' => end($parts)]);

        return new PermissionGrantData($permission->id, $scope ?? PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $user->id, schoolId: $f['school']->id, grants: $grants));

    return [$user, $staff];
}

/**
 * @param  array<string, mixed>  $f
 */
function supAdminTerm(array $f): void
{
    $f['term']->update(['starts_on' => now()->subWeeks(6)->startOfDay(), 'ends_on' => now()->addWeeks(6)->startOfDay()]);
}

/**
 * @param  array<string, mixed>  $f
 */
function supAdminScheme(array $f, Staff $teacher, string $status = 'draft'): SchemeOfWork
{
    $scheme = SchemeOfWork::create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'subject_id' => $f['subject']->id,
        'grade_level_id' => GradeLevel::factory()->for($f['school'])->create()->id, 'teacher_staff_id' => $teacher->id,
        'planned_topics' => [['week' => 1, 'topic' => 'Forces'], ['week' => 2, 'topic' => 'Energy']], 'status' => $status,
    ]);

    foreach ([0, 1] as $index) {
        SyllabusCoverageRecord::create(['school_id' => $f['school']->id, 'scheme_of_work_id' => $scheme->id, 'planned_topic_index' => $index]);
    }

    return $scheme;
}

it('refuses every supervision screen to a user without a supervision permission', function (string $component): void {
    $f = aca11Fixture();
    [$user] = supAdminPerson($f, ['cbt.bank.manage']);
    $this->actingAs($user);

    Livewire::test($component, ['school' => $f['school']])->assertForbidden();
})->with([SchemeScreen::class, LessonPlans::class, Coverage::class, Observe::class, ObservationHistory::class, Meetings::class, TeacherDashboard::class]);

it('takes a scheme from draft to approved, refusing self-approval and a return without a reason', function (): void {
    $f = aca11Fixture();
    supAdminTerm($f);
    $grade = GradeLevel::factory()->for($f['school'])->create();
    [$teacherUser, $teacher] = supAdminPerson($f, ['supervision.plan', 'supervision.scheme.approve']);
    [$hodUser] = supAdminPerson($f, ['supervision.scheme.approve']);

    $this->actingAs($teacherUser);
    $page = Livewire::test(SchemeScreen::class, ['school' => $f['school']])
        ->set('subjectId', $f['subject']->id)->set('gradeLevelId', $grade->id)->set('topicsText', "1 | Forces | Newton's laws\n2 | Energy")->call('save')->assertHasNoErrors();

    $scheme = SchemeOfWork::where('teacher_staff_id', $teacher->id)->firstOrFail();
    expect($scheme->planned_topics)->toHaveCount(2)->and(SyllabusCoverageRecord::where('scheme_of_work_id', $scheme->id)->count())->toBe(2);

    $page->set('subjectId', $f['subject']->id)->set('gradeLevelId', $grade->id)->set('topicsText', '1 | Forces')->call('save')->assertHasErrors('topicsText');
    $page->set('topicsText', '')->set('editingId', null)->set('subjectId', $f['subject']->id)->set('gradeLevelId', $grade->id)->call('save')->assertHasErrors('topicsText');

    $page->call('submit', $scheme->id);
    expect($scheme->fresh()->status)->toBe('submitted');

    $page->call('approve', $scheme->id);
    expect($scheme->fresh()->status)->toBe('submitted');

    $this->actingAs($hodUser);
    $hod = Livewire::test(SchemeScreen::class, ['school' => $f['school']]);
    $hod->call('startReturn', $scheme->id)->set('returnComment', '  ')->call('sendBack');
    expect($scheme->fresh()->status)->toBe('submitted');

    $hod->set('returnComment', 'Add objectives')->call('sendBack');
    expect($scheme->fresh()->status)->toBe('returned');

    $this->actingAs($teacherUser);
    Livewire::test(SchemeScreen::class, ['school' => $f['school']])->call('edit', $scheme->id)->set('topicsText', "1 | Forces | Laws\n2 | Energy | Work\n3 | Power")->call('save')->assertHasNoErrors()->call('submit', $scheme->id);
    expect($scheme->fresh()->planned_topics)->toHaveCount(3)->and($scheme->fresh()->status)->toBe('submitted');

    $this->actingAs($hodUser);
    Livewire::test(SchemeScreen::class, ['school' => $f['school']])->call('approve', $scheme->id);
    expect($scheme->fresh()->status)->toBe('approved');
});

it('blocks a linked lesson plan until its scheme is approved, and reviewers cannot review their own plan', function (): void {
    $f = aca11Fixture();
    supAdminTerm($f);
    [$teacherUser, $teacher] = supAdminPerson($f, ['supervision.plan', 'supervision.scheme.approve']);
    [$hodUser] = supAdminPerson($f, ['supervision.scheme.approve']);
    $scheme = supAdminScheme($f, $teacher, 'submitted');

    $this->actingAs($teacherUser);
    $page = Livewire::test(LessonPlans::class, ['school' => $f['school']])->set('topic', 'Newton')->set('schemeId', $scheme->id)->call('create')->assertHasNoErrors();
    $plan = LessonPlan::where('teacher_staff_id', $teacher->id)->firstOrFail();

    $page->call('submit', $plan->id);
    expect($plan->fresh()->status)->toBe('draft');

    $scheme->update(['status' => 'approved']);
    $page->call('submit', $plan->id);
    expect($plan->fresh()->status)->toBe('submitted');

    $page->call('startReview', $plan->id)->call('review');
    expect($plan->fresh()->status)->toBe('submitted');

    $this->actingAs($hodUser);
    Livewire::test(LessonPlans::class, ['school' => $f['school']])->call('startReview', $plan->id)->set('hodComments', 'Good')->call('review');
    expect($plan->fresh()->status)->toBe('reviewed')->and($plan->fresh()->hod_comments)->toBe('Good');
});

it('will not link a lesson plan to another teacher\'s scheme or submit another teacher\'s plan', function (): void {
    $f = aca11Fixture();
    [$aliceUser] = supAdminPerson($f, ['supervision.plan']);
    [$bobUser, $bob] = supAdminPerson($f, ['supervision.plan']);
    $bobScheme = supAdminScheme($f, $bob, 'approved');
    $bobPlan = LessonPlan::create(['school_id' => $f['school']->id, 'teacher_staff_id' => $bob->id, 'lesson_date' => now()->toDateString(), 'topic' => 'Bob', 'status' => 'draft']);

    $this->actingAs($aliceUser);
    $page = Livewire::test(LessonPlans::class, ['school' => $f['school']])->set('topic', 'Mine')->set('schemeId', $bobScheme->id)->call('create')->assertHasErrors('topic');
    $page->call('submit', $bobPlan->id);

    expect($bobPlan->fresh()->status)->toBe('draft');
});

it('lets a teacher record delivery on their own scheme and shows a late topic as behind', function (): void {
    $f = aca11Fixture();
    supAdminTerm($f);
    [$teacherUser, $teacher] = supAdminPerson($f, ['supervision.plan']);
    [$otherUser] = supAdminPerson($f, ['supervision.plan']);
    $scheme = supAdminScheme($f, $teacher, 'approved');
    $late = now()->subWeeks(1)->toDateString();

    $this->actingAs($teacherUser);
    $page = Livewire::test(Coverage::class, ['school' => $f['school']])
        ->set('dates.'.$scheme->id.':0', $late)->set('notes.'.$scheme->id.':0', 'delayed')->call('record', $scheme->id, 0);

    $record = SyllabusCoverageRecord::where('scheme_of_work_id', $scheme->id)->where('planned_topic_index', 0)->firstOrFail();
    expect($record->actual_delivered_on->toDateString())->toBe($late)->and($record->variance_note)->toBe('delayed');
    $page->assertSee('behind');

    $page->set('dates.'.$scheme->id.':1', now()->addDays(3)->toDateString())->call('record', $scheme->id, 1);
    expect(SyllabusCoverageRecord::where('scheme_of_work_id', $scheme->id)->where('planned_topic_index', 1)->value('actual_delivered_on'))->toBeNull();

    $this->actingAs($otherUser);
    Livewire::test(Coverage::class, ['school' => $f['school']])->assertDontSee('behind')->call('record', $scheme->id, 1)->assertForbidden();
});

/**
 * @param  array<string, mixed>  $f
 */
function supAdminRubric(array $f): ObservationRubric
{
    return ObservationRubric::create(['school_id' => $f['school']->id, 'name' => 'Standard', 'criteria' => [
        ['criterion' => 'Planning', 'descriptor_levels' => ['emerging', 'proficient']],
        ['criterion' => 'Delivery', 'descriptor_levels' => ['emerging', 'proficient']],
    ]]);
}

it('records an observation scoring every criterion and refuses self-observation, a missing score and a rubric that does not exist', function (): void {
    $f = aca11Fixture();
    $f['term']->update(['starts_on' => now()->subWeeks(6)]);
    [$observerUser, $observer] = supAdminPerson($f, ['supervision.observe']);
    $teacher = Staff::factory()->for($f['school'])->create();
    $rubric = supAdminRubric($f);
    $this->actingAs($observerUser);

    $page = Livewire::test(Observe::class, ['school' => $f['school']])->set('rubricId', $rubric->id)->set('scores', [0 => 'proficient', 1 => 'emerging']);
    $page->set('observedStaffId', $observer->id)->call('record')->assertHasErrors('observedStaffId');

    $page->set('observedStaffId', $teacher->id)->set('scores', [0 => 'proficient'])->call('record')->assertHasErrors('observedStaffId');
    expect(LessonObservation::count())->toBe(0);

    $page->set('scores', [0 => 'proficient', 1 => 'emerging'])->set('rating', 'good')->call('record')->assertHasNoErrors();
    $observation = LessonObservation::firstOrFail();
    expect($observation->observer_staff_id)->toBe($observer->id)->and($observation->scores)->toBe(['Planning' => 'proficient', 'Delivery' => 'emerging']);

    $page->set('observedStaffId', $teacher->id)->set('rubricId', 999999)->call('record')->assertHasErrors('observedStaffId');
});

it('links a follow-up to the earlier observation and only a rubric manager can create rubrics', function (): void {
    $f = aca11Fixture();
    $f['term']->update(['starts_on' => now()->subWeeks(6)]);
    [$observerUser, $observer] = supAdminPerson($f, ['supervision.observe']);
    $teacher = Staff::factory()->for($f['school'])->create();
    $rubric = supAdminRubric($f);
    $this->actingAs($observerUser);

    $page = Livewire::test(Observe::class, ['school' => $f['school']])->set('rubricId', $rubric->id)->set('observedStaffId', $teacher->id)->set('scores', [0 => 'emerging', 1 => 'emerging'])->set('observedAt', now()->subDays(10)->format('Y-m-d\TH:i'));
    $page->call('record');
    $first = LessonObservation::firstOrFail();

    $page->set('observedStaffId', $teacher->id)->set('scores', [0 => 'proficient', 1 => 'proficient'])->set('observedAt', now()->subDay()->format('Y-m-d\TH:i'))->set('followUpId', $first->id)->call('record')->assertHasNoErrors();
    expect(LessonObservation::whereNotNull('follow_up_observation_id')->value('follow_up_observation_id'))->toBe($first->id);

    $page->set('rubricName', 'X')->call('createRubric')->assertForbidden();
});

it('lets the observed teacher comment on their record without being able to touch the scores, and hides it from colleagues', function (): void {
    $f = aca11Fixture();
    $f['term']->update(['starts_on' => now()->subWeeks(6)]);
    [$teacherUser, $teacher] = supAdminPerson($f, ['supervision.plan']);
    [$colleagueUser] = supAdminPerson($f, ['supervision.plan']);
    [, $observer] = supAdminPerson($f, ['supervision.observe']);
    $rubric = supAdminRubric($f);
    $observation = LessonObservation::create([
        'school_id' => $f['school']->id, 'term_id' => $f['term']->id, 'observed_staff_id' => $teacher->id, 'observer_staff_id' => $observer->id, 'rubric_id' => $rubric->id,
        'observed_at' => now()->subDay(), 'scores' => ['Planning' => 'emerging', 'Delivery' => 'proficient'], 'overall_rating' => 'good', 'teacher_acknowledged' => false,
    ]);

    $this->actingAs($teacherUser);
    Livewire::test(ObservationHistory::class, ['school' => $f['school']])->assertSee('emerging')->call('startComment', $observation->id)->set('commentText', 'Fair point')->call('saveComment')->assertHasNoErrors();
    $fresh = $observation->fresh();
    expect($fresh->teacher_comments)->toBe('Fair point')->and($fresh->scores)->toBe(['Planning' => 'emerging', 'Delivery' => 'proficient']);

    $this->actingAs($colleagueUser);
    $colleague = Livewire::test(ObservationHistory::class, ['school' => $f['school']])->assertDontSee('emerging');
    $colleague->set('commentingId', $observation->id)->set('commentText', 'Hijack')->call('saveComment')->assertForbidden();
    expect($observation->fresh()->teacher_comments)->toBe('Fair point');
});

it('shows a head of department their own department\'s observations and dashboard, and school-reach viewers everything', function (): void {
    $f = aca11Fixture();
    $f['term']->update(['starts_on' => now()->subWeeks(6)]);
    [$hodUser, $hod] = supAdminPerson($f, ['supervision.view'], PermissionScope::Section);
    $department = Department::factory()->for($f['school'])->create(['head_staff_id' => $hod->id]);
    $inside = Staff::factory()->for($f['school'])->create(['department_id' => $department->id, 'first_name' => 'Inside']);
    $outside = Staff::factory()->for($f['school'])->create(['first_name' => 'Outside']);
    [, $observer] = supAdminPerson($f, ['supervision.observe']);
    $rubric = supAdminRubric($f);

    foreach ([$inside, $outside] as $member) {
        LessonObservation::create([
            'school_id' => $f['school']->id, 'term_id' => $f['term']->id, 'observed_staff_id' => $member->id, 'observer_staff_id' => $observer->id, 'rubric_id' => $rubric->id,
            'observed_at' => now()->subDay(), 'scores' => ['Planning' => 'emerging', 'Delivery' => 'emerging'], 'teacher_acknowledged' => false,
        ]);
    }

    $this->actingAs($hodUser);
    Livewire::test(ObservationHistory::class, ['school' => $f['school']])->assertSee('Inside')->assertDontSee('Outside');
    Livewire::test(TeacherDashboard::class, ['school' => $f['school']])->assertSee('Inside')->assertDontSee('Outside');

    [$headUser] = supAdminPerson($f, ['supervision.view']);
    $this->actingAs($headUser);
    Livewire::test(TeacherDashboard::class, ['school' => $f['school']])->assertSee('Inside')->assertSee('Outside');
});

it('records minutes with action items that the owner can move along but a bystander cannot', function (): void {
    $f = aca11Fixture();
    [$managerUser, $manager] = supAdminPerson($f, ['supervision.meeting.manage']);
    [$ownerUser, $owner] = supAdminPerson($f, ['supervision.plan']);
    [$bystanderUser] = supAdminPerson($f, ['supervision.plan']);
    $department = Department::factory()->for($f['school'])->create();

    $this->actingAs($managerUser);
    $page = Livewire::test(Meetings::class, ['school' => $f['school']])
        ->set('departmentId', $department->id)->set('chairId', $manager->id)->set('attendees', [$manager->id, $owner->id])->set('minutes', 'Agreed to moderate')
        ->set('actionRows', [['action' => 'Moderate scripts', 'owner' => (string) $owner->id, 'due' => now()->addWeek()->toDateString()]])
        ->call('record')->assertHasNoErrors();

    $meeting = DepartmentMeeting::firstOrFail();
    expect($meeting->action_items[0]['status'])->toBe('open');

    $page->set('minutes', '')->call('record')->assertHasErrors('minutes');

    $this->actingAs($ownerUser);
    Livewire::test(Meetings::class, ['school' => $f['school']])->assertSee('Moderate scripts')->call('setStatus', $meeting->id, 0, 'done');
    expect($meeting->fresh()->action_items[0]['status'])->toBe('done')->and($meeting->fresh()->minutes)->toBe('Agreed to moderate');

    $this->actingAs($bystanderUser);
    Livewire::test(Meetings::class, ['school' => $f['school']])->call('setStatus', $meeting->id, 0, 'open')->assertForbidden();
    expect($meeting->fresh()->action_items[0]['status'])->toBe('done');
});
