<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Modules\Academic\Domain\Actions\ChaseNonSubmittersAction;
use Modules\Academic\Domain\Actions\PostDiscussionMessageAction;
use Modules\Academic\Domain\DataObjects\ChaseNonSubmittersData;
use Modules\Academic\Domain\DataObjects\PostDiscussionMessageData;
use Modules\Academic\Livewire\Lms\AssignmentCreate;
use Modules\Academic\Livewire\Lms\CourseSpace as CourseSpaceScreen;
use Modules\Academic\Livewire\Lms\CourseSpaces;
use Modules\Academic\Livewire\Lms\Discussion;
use Modules\Academic\Livewire\Lms\Marking;
use Modules\Academic\Livewire\Lms\NonSubmission;
use Modules\Academic\Models\Assignment;
use Modules\Academic\Models\AssignmentSubmission;
use Modules\Academic\Models\ContentItem;
use Modules\Academic\Models\CourseSpace;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\DiscussionPost;
use Modules\Academic\Models\DiscussionThread;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TeachingGroup;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\File;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * Book K ACA-08 admin-UI pass. Own, distinctly-named helpers.
 *
 * @return array<string, mixed>
 */
function lmsAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $framework = CurriculumFramework::factory()->for($school)->create();
    $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id, 'name' => 'Physics']);
    $teacherUser = User::factory()->create();
    $teacher = Staff::factory()->for($school)->create(['user_id' => $teacherUser->id]);
    $group = TeachingGroup::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'grade_level_id' => $gradeLevel->id, 'teacher_staff_id' => $teacher->id, 'name' => 'Form 3 Physics']);
    $space = CourseSpace::factory()->create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'teaching_group_id' => $group->id, 'teacher_staff_id' => $teacher->id]);

    return compact('school', 'year', 'term', 'gradeLevel', 'subject', 'teacherUser', 'teacher', 'group', 'space');
}

/**
 * @param  array<string, mixed>  $f
 */
function lmsAdminGrant(array $f, User $user, PermissionScope $scope, string ...$permissionNames): User
{
    $user->schools()->syncWithoutDetaching([$f['school']->id => ['status' => 'active']]);

    $grants = array_map(function (string $name) use ($scope): PermissionGrantData {
        $parts = explode('.', $name);
        $permission = Permission::firstOrCreate(['name' => $name], ['guard_name' => 'web', 'module_code' => strtoupper($parts[0]), 'resource' => $parts[1], 'action' => end($parts)]);

        return new PermissionGrantData($permission->id, $scope);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $user->id, schoolId: $f['school']->id, grants: $grants));

    return $user;
}

/**
 * @param  array<string, mixed>  $f
 */
function lmsAdminAdmin(array $f): User
{
    return lmsAdminGrant($f, User::factory()->create(), PermissionScope::School, 'lms.course.manage', 'lms.assignment.create', 'lms.assignment.mark', 'lms.discussion.moderate');
}

/**
 * @param  array<string, mixed>  $f
 */
function lmsAdminTeacher(array $f): User
{
    return lmsAdminGrant($f, $f['teacherUser'], PermissionScope::Own, 'lms.course.manage', 'lms.assignment.create', 'lms.assignment.mark');
}

/**
 * @param  array<string, mixed>  $f
 * @param  array<string, mixed>  $overrides
 */
function lmsAdminAssignment(array $f, array $overrides = []): Assignment
{
    return Assignment::factory()->create($overrides + ['school_id' => $f['school']->id, 'course_space_id' => $f['space']->id]);
}

/**
 * @param  array<string, mixed>  $f
 */
function lmsAdminEnrolled(array $f): Student
{
    $student = Student::factory()->for($f['school'])->create();
    TeachingGroupMember::factory()->create(['school_id' => $f['school']->id, 'teaching_group_id' => $f['group']->id, 'student_id' => $student->id]);

    return $student;
}

it('refuses every LMS screen to a user without its permission', function (string $component, bool $needsSpace, bool $needsAssignment): void {
    $f = lmsAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);
    $params = ['school' => $f['school']];

    if ($needsSpace) {
        $params['space'] = $f['space']->id;
    }

    if ($needsAssignment) {
        $params['assignment'] = lmsAdminAssignment($f)->id;
    }

    Livewire::actingAs($outsider)->test($component, $params)->assertForbidden();
})->with([
    'spaces' => [CourseSpaces::class, false, false],
    'space' => [CourseSpaceScreen::class, true, false],
    'create' => [AssignmentCreate::class, true, false],
    'marking' => [Marking::class, false, true],
    'non-submission' => [NonSubmission::class, false, true],
    'discussion' => [Discussion::class, true, false],
]);

it('lets a teacher see and open only the spaces they teach, and an administrator all of them', function (): void {
    $f = lmsAdminFixture();
    $teacher = lmsAdminTeacher($f);
    $otherTeacher = Staff::factory()->for($f['school'])->create();
    $otherGroup = TeachingGroup::factory()->create(['school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'subject_id' => $f['subject']->id, 'grade_level_id' => $f['gradeLevel']->id, 'teacher_staff_id' => $otherTeacher->id, 'name' => 'Form 4 Chemistry']);
    $otherSpace = CourseSpace::factory()->create(['school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'subject_id' => $f['subject']->id, 'teaching_group_id' => $otherGroup->id, 'teacher_staff_id' => $otherTeacher->id]);

    Livewire::actingAs($teacher)->test(CourseSpaces::class, ['school' => $f['school']])->assertSee('Form 3 Physics')->assertDontSee('Form 4 Chemistry');
    Livewire::actingAs($teacher)->test(CourseSpaceScreen::class, ['school' => $f['school'], 'space' => $otherSpace->id])->assertForbidden();

    Livewire::actingAs(lmsAdminAdmin($f))->test(CourseSpaces::class, ['school' => $f['school']])->assertSee('Form 3 Physics')->assertSee('Form 4 Chemistry');
});

it('creates a course space for a group a teacher teaches, not for someone else’s, and never twice', function (): void {
    $f = lmsAdminFixture();
    $teacher = lmsAdminTeacher($f);
    $fresh = TeachingGroup::factory()->create(['school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'subject_id' => $f['subject']->id, 'grade_level_id' => $f['gradeLevel']->id, 'teacher_staff_id' => $f['teacher']->id]);
    $foreign = TeachingGroup::factory()->create(['school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id, 'subject_id' => $f['subject']->id, 'grade_level_id' => $f['gradeLevel']->id, 'teacher_staff_id' => Staff::factory()->for($f['school'])->create()->id]);

    $component = Livewire::actingAs($teacher)->test(CourseSpaces::class, ['school' => $f['school']])->set('teachingGroupId', $fresh->id)->call('create')->assertHasNoErrors();
    expect(CourseSpace::where('teaching_group_id', $fresh->id)->count())->toBe(1);

    $component->set('teachingGroupId', $foreign->id)->call('create')->assertForbidden();
    expect(CourseSpace::where('teaching_group_id', $foreign->id)->exists())->toBeFalse();

    Livewire::actingAs(lmsAdminAdmin($f))->test(CourseSpaces::class, ['school' => $f['school']])->set('teachingGroupId', $fresh->id)->call('create')->assertHasErrors('teachingGroupId');
});

it('shows size before download, flags large and stream-only content, and refuses unsafe links (AC-ACA-08-001)', function (): void {
    $f = lmsAdminFixture();
    $teacher = lmsAdminTeacher($f);
    $video = File::factory()->create(['school_id' => $f['school']->id, 'original_name' => 'Circular Motion.mp4', 'size_bytes' => 18_000_000, 'scan_status' => 'clean']);

    $component = Livewire::actingAs($teacher)->test(CourseSpaceScreen::class, ['school' => $f['school'], 'space' => $f['space']->id])
        ->set('contentType', 'video')->set('title', 'Practical Demo')->set('fileId', $video->id)->set('offline', false)->call('addContent')->assertHasNoErrors()
        ->assertSee('17.2 MB')->assertSee('Large file')->assertSee('Stream only');

    expect(ContentItem::firstOrFail())->file_size_bytes->toBe(18_000_000)->published_at->toBeNull()->is_downloadable_offline->toBeFalse();

    $component->set('contentType', 'link')->set('title', 'Bad')->set('fileId', null)->set('externalUrl', 'javascript:alert(1)')->call('addContent')->assertHasErrors('title');
    $component->set('externalUrl', '')->call('addContent')->assertHasErrors('title');
    expect(ContentItem::count())->toBe(1);
});

it('will not publish a file that has not passed its virus scan, and refuses an infected one outright (BR-ACA-08-011)', function (): void {
    $f = lmsAdminFixture();
    $teacher = lmsAdminTeacher($f);
    $pending = File::factory()->create(['school_id' => $f['school']->id, 'scan_status' => 'pending']);
    $infected = File::factory()->create(['school_id' => $f['school']->id, 'scan_status' => 'infected']);

    $component = Livewire::actingAs($teacher)->test(CourseSpaceScreen::class, ['school' => $f['school'], 'space' => $f['space']->id])
        ->set('title', 'Notes')->set('fileId', $pending->id)->call('addContent')->assertHasNoErrors();

    $item = ContentItem::firstOrFail();
    $component->call('publishContent', $item->id);
    expect($item->fresh()->published_at)->toBeNull();

    $pending->update(['scan_status' => 'clean']);
    $component->call('publishContent', $item->id);
    expect($item->fresh()->published_at)->not->toBeNull();

    $component->set('title', 'Virus')->set('fileId', $infected->id)->call('addContent')->assertHasErrors('title');
    expect(ContentItem::count())->toBe(1);
});

it('cannot attach another school’s file or publish content from another space', function (): void {
    $f = lmsAdminFixture();
    $teacher = lmsAdminTeacher($f);
    $foreignFile = File::factory()->create(['school_id' => School::factory()->create()->id, 'scan_status' => 'clean']);
    SchoolContext::set($f['school']);
    $otherSpace = CourseSpace::factory()->create(['school_id' => $f['school']->id, 'teacher_staff_id' => Staff::factory()->for($f['school'])->create()->id]);
    $foreignItem = ContentItem::factory()->create(['school_id' => $f['school']->id, 'course_space_id' => $otherSpace->id, 'external_url' => 'https://example.test/x']);

    $component = Livewire::actingAs($teacher)->test(CourseSpaceScreen::class, ['school' => $f['school'], 'space' => $f['space']->id]);

    expect(fn () => $component->set('title', 'Stolen')->set('fileId', $foreignFile->id)->call('addContent'))->toThrow(ModelNotFoundException::class);
    expect(fn () => $component->call('publishContent', $foreignItem->id))->toThrow(ModelNotFoundException::class);
});

it('creates a draft assignment, publishes it and closes it, validating dates, penalty and gradebook link', function (): void {
    $f = lmsAdminFixture();
    $teacher = lmsAdminTeacher($f);

    $component = Livewire::actingAs($teacher)->test(AssignmentCreate::class, ['school' => $f['school'], 'space' => $f['space']->id])
        ->set('title', 'Essay 1')->set('instructions', 'Write.')->set('opensAt', now()->format('Y-m-d\TH:i'))->set('dueAt', now()->subDay()->format('Y-m-d\TH:i'))
        ->call('create')->assertHasErrors('title');
    expect(Assignment::count())->toBe(0);

    $component->set('dueAt', now()->addWeek()->format('Y-m-d\TH:i'))->set('latePolicy', 'accept_penalised')->set('penalty', '150')->call('create')->assertHasErrors('penalty');
    $component->set('penalty', '10')->call('create')->assertHasNoErrors();

    $assignment = Assignment::firstOrFail();
    expect($assignment->status)->toBe('draft')->and($assignment->late_policy)->toBe('accept_penalised');

    $screen = Livewire::actingAs($teacher)->test(CourseSpaceScreen::class, ['school' => $f['school'], 'space' => $f['space']->id])->call('publishAssignment', $assignment->id);
    expect($assignment->fresh()->status)->toBe('published');

    $screen->call('closeAssignment', $assignment->id);
    expect($assignment->fresh()->status)->toBe('closed');

    $screen->call('closeAssignment', $assignment->id);
    expect($assignment->fresh()->status)->toBe('closed');
});

it('applies the late penalty once at marking and only flags similarity (AC-ACA-08-002/003)', function (): void {
    $f = lmsAdminFixture();
    $teacher = lmsAdminTeacher($f);
    $assignment = lmsAdminAssignment($f, ['late_policy' => 'accept_penalised', 'late_penalty_percent_per_day' => 10, 'max_mark' => 100, 'due_at' => now()->subDays(2)]);
    $late = AssignmentSubmission::factory()->create(['school_id' => $f['school']->id, 'assignment_id' => $assignment->id, 'student_id' => Student::factory()->for($f['school'])->create()->id, 'is_late' => true, 'minutes_late' => 2 * 1440 - 60, 'status' => 'submitted']);
    $copy = AssignmentSubmission::factory()->create(['school_id' => $f['school']->id, 'assignment_id' => $assignment->id, 'student_id' => Student::factory()->for($f['school'])->create()->id, 'similarity_flag' => true, 'similarity_matches' => [$late->id], 'status' => 'submitted']);

    $component = Livewire::actingAs($teacher)->test(Marking::class, ['school' => $f['school'], 'assignment' => $assignment->id])->assertSee('Similar to 1 other');

    $component->call('begin', $late->id)->set('rawMark', '80')->set('feedback', 'Good argument.')->call('mark')->assertHasNoErrors();
    expect($late->fresh())->penalty_applied_percent->toEqual(20.0)->final_mark->toEqual(64.0)->status->toBe('marked');

    $component->call('begin', $copy->id)->set('rawMark', '90')->call('mark')->assertHasNoErrors();
    expect($copy->fresh())->final_mark->toEqual(90.0)->penalty_applied_percent->toEqual(0.0);
});

it('refuses a mark above the maximum and any submission that is not this assignment’s', function (): void {
    $f = lmsAdminFixture();
    $teacher = lmsAdminTeacher($f);
    $assignment = lmsAdminAssignment($f, ['max_mark' => 50]);
    $other = lmsAdminAssignment($f);
    $mine = AssignmentSubmission::factory()->create(['school_id' => $f['school']->id, 'assignment_id' => $assignment->id, 'student_id' => Student::factory()->for($f['school'])->create()->id]);
    $foreign = AssignmentSubmission::factory()->create(['school_id' => $f['school']->id, 'assignment_id' => $other->id, 'student_id' => Student::factory()->for($f['school'])->create()->id]);

    $component = Livewire::actingAs($teacher)->test(Marking::class, ['school' => $f['school'], 'assignment' => $assignment->id])->call('begin', $mine->id)->set('rawMark', '75')->call('mark')->assertHasErrors('rawMark');
    expect($mine->fresh()->final_mark)->toBeNull();

    expect(fn () => $component->call('begin', $foreign->id))->toThrow(ModelNotFoundException::class);
});

it('keeps another teacher out of marking and non-submission for a space they do not teach', function (): void {
    $f = lmsAdminFixture();
    $assignment = lmsAdminAssignment($f);
    $stranger = lmsAdminGrant($f, User::factory()->create(), PermissionScope::Own, 'lms.assignment.mark');
    Staff::factory()->for($f['school'])->create(['user_id' => $stranger->id]);

    Livewire::actingAs($stranger)->test(Marking::class, ['school' => $f['school'], 'assignment' => $assignment->id])->assertForbidden();
    Livewire::actingAs($stranger)->test(NonSubmission::class, ['school' => $f['school'], 'assignment' => $assignment->id])->assertForbidden();
});

it('lists non-submitters only after the due date and reminds only genuine non-submitters (BR-ACA-08-009)', function (): void {
    $f = lmsAdminFixture();
    $teacher = lmsAdminTeacher($f);
    $absent = lmsAdminEnrolled($f);
    $absent->update(['first_name' => 'Absent', 'last_name' => 'Learner']);
    $done = lmsAdminEnrolled($f);
    $done->update(['first_name' => 'Done', 'last_name' => 'Already']);
    $outsider = Student::factory()->for($f['school'])->create(['first_name' => 'Not', 'last_name' => 'InClass']);

    $open = lmsAdminAssignment($f, ['due_at' => now()->addDay()]);
    Livewire::actingAs($teacher)->test(NonSubmission::class, ['school' => $f['school'], 'assignment' => $open->id])->assertSee('Nobody is outstanding');

    $assignment = lmsAdminAssignment($f, ['due_at' => now()->subDay()]);
    AssignmentSubmission::factory()->create(['school_id' => $f['school']->id, 'assignment_id' => $assignment->id, 'student_id' => $done->id]);

    Livewire::actingAs($teacher)->test(NonSubmission::class, ['school' => $f['school'], 'assignment' => $assignment->id])->assertSee('Absent Learner')->assertDontSee('Done Already')->assertDontSee('InClass')->call('chase');

    foreach ([$absent, $done, $outsider] as $student) {
        $student->update(['user_id' => User::factory()->create()->id]);
    }

    $sent = app(ChaseNonSubmittersAction::class)->execute(new ChaseNonSubmittersData($assignment->id, [$absent->id, $done->id, $outsider->id]));

    expect($sent)->toBeLessThanOrEqual(1);
});

it('lets only the course teacher post as staff, moderators hide with a reason, and a locked thread takes no posts (BR-ACA-08-010)', function (): void {
    $f = lmsAdminFixture();
    $teacher = lmsAdminTeacher($f);
    lmsAdminGrant($f, $f['teacherUser'], PermissionScope::School, 'lms.course.manage', 'lms.discussion.moderate');
    $admin = lmsAdminAdmin($f);

    $component = Livewire::actingAs($teacher)->test(Discussion::class, ['school' => $f['school'], 'space' => $f['space']->id])
        ->set('newTitle', 'Momentum questions')->call('createThread')->assertHasNoErrors()
        ->set('message', 'Read chapter 4 first.')->call('reply')->assertHasNoErrors()->assertSee('Read chapter 4 first.');

    $post = DiscussionPost::firstOrFail();
    $thread = DiscussionThread::firstOrFail();

    $component->call('beginHide', $post->id)->set('hideReason', '')->call('hide')->assertHasErrors('hideReason');
    expect($post->fresh()->is_hidden)->toBeFalse();

    $component->set('hideReason', 'Off topic.')->call('hide')->assertHasNoErrors();
    expect($post->fresh())->is_hidden->toBeTrue()->hidden_reason->toBe('Off topic.')->hidden_by->toBe($f['teacherUser']->id);

    $component->call('setState', $thread->id, 'lock');
    expect($thread->fresh()->is_locked)->toBeTrue();

    $adminView = Livewire::actingAs($admin)->test(Discussion::class, ['school' => $f['school'], 'space' => $f['space']->id])->call('openThread', $thread->id)->assertSee('Hidden by a moderator');
    $adminView->set('message', 'I am not the teacher.')->call('reply')->assertHasErrors('message');
    expect(DiscussionPost::count())->toBe(1);
});

it('refuses discussion posts by someone who is not the teacher or an enrolled learner (Action level)', function (): void {
    $f = lmsAdminFixture();
    $thread = DiscussionThread::factory()->create(['school_id' => $f['school']->id, 'course_space_id' => $f['space']->id]);
    $stranger = Student::factory()->for($f['school'])->create();
    $otherStaff = Staff::factory()->for($f['school'])->create();

    expect(fn () => app(PostDiscussionMessageAction::class)->execute(new PostDiscussionMessageData($thread->id, 'student', $stranger->id, 'hello')))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(PostDiscussionMessageAction::class)->execute(new PostDiscussionMessageData($thread->id, 'staff', $otherStaff->id, 'hello')))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(PostDiscussionMessageAction::class)->execute(new PostDiscussionMessageData($thread->id, 'parent', $f['teacher']->id, 'hello')))->toThrow(InvalidArgumentException::class);

    $enrolled = lmsAdminEnrolled($f);
    expect(app(PostDiscussionMessageAction::class)->execute(new PostDiscussionMessageData($thread->id, 'student', $enrolled->id, 'hello'))->content)->toBe('hello');
});
