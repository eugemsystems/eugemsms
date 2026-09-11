<?php

use App\Models\User;
use Modules\Academic\Domain\Actions\ChaseNonSubmittersAction;
use Modules\Academic\Domain\Actions\CreateAssignmentAction;
use Modules\Academic\Domain\Actions\CreateContentItemAction;
use Modules\Academic\Domain\Actions\CreateCourseSpaceAction;
use Modules\Academic\Domain\Actions\CreateDiscussionThreadAction;
use Modules\Academic\Domain\Actions\HideDiscussionPostAction;
use Modules\Academic\Domain\Actions\ListAccessibleContentAction;
use Modules\Academic\Domain\Actions\ListNonSubmittersAction;
use Modules\Academic\Domain\Actions\MarkAssignmentSubmissionAction;
use Modules\Academic\Domain\Actions\PostDiscussionMessageAction;
use Modules\Academic\Domain\Actions\PublishAssignmentAction;
use Modules\Academic\Domain\Actions\PublishContentItemAction;
use Modules\Academic\Domain\Actions\RecordContentDownloadAction;
use Modules\Academic\Domain\Actions\SubmitAssignmentAction;
use Modules\Academic\Domain\DataObjects\ChaseNonSubmittersData;
use Modules\Academic\Domain\DataObjects\CreateAssignmentData;
use Modules\Academic\Domain\DataObjects\CreateContentItemData;
use Modules\Academic\Domain\DataObjects\CreateCourseSpaceData;
use Modules\Academic\Domain\DataObjects\CreateDiscussionThreadData;
use Modules\Academic\Domain\DataObjects\HideDiscussionPostData;
use Modules\Academic\Domain\DataObjects\ListAccessibleContentData;
use Modules\Academic\Domain\DataObjects\ListNonSubmittersData;
use Modules\Academic\Domain\DataObjects\MarkAssignmentSubmissionData;
use Modules\Academic\Domain\DataObjects\PostDiscussionMessageData;
use Modules\Academic\Domain\DataObjects\PublishAssignmentData;
use Modules\Academic\Domain\DataObjects\PublishContentItemData;
use Modules\Academic\Domain\DataObjects\RecordContentDownloadData;
use Modules\Academic\Domain\DataObjects\SubmitAssignmentData;
use Modules\Academic\Domain\Exceptions\AssignmentSubmissionBlockedException;
use Modules\Academic\Domain\Exceptions\ContentNotDownloadableOfflineException;
use Modules\Academic\Domain\Exceptions\DuplicateCourseSpaceException;
use Modules\Academic\Domain\Exceptions\LearnerNotEnrolledException;
use Modules\Academic\Domain\Exceptions\ResubmissionNotAllowedException;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\AssessmentType;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\DiscussionPost;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TeachingGroup;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * @return array{school: School, year: AcademicYear, term: Term, subject: Subject, teachingGroup: TeachingGroup, teacher: Staff, user: User}
 */
function aca08Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $framework = CurriculumFramework::factory()->for($school)->create();
    $subject = Subject::factory()->for($school)->create(['framework_id' => $framework->id]);
    $teacher = Staff::factory()->for($school)->create();
    $teachingGroup = TeachingGroup::factory()->create([
        'school_id' => $school->id, 'academic_year_id' => $year->id, 'term_id' => $term->id,
        'subject_id' => $subject->id, 'grade_level_id' => $gradeLevel->id, 'teacher_staff_id' => $teacher->id,
    ]);
    $user = User::factory()->create();

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'subject' => $subject,
        'teachingGroup' => $teachingGroup, 'teacher' => $teacher, 'user' => $user,
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function aca08EnrolledStudent(array $f): Student
{
    $userAccount = User::factory()->create();
    $student = Student::factory()->for($f['school'])->create(['user_id' => $userAccount->id]);

    TeachingGroupMember::factory()->create([
        'school_id' => $f['school']->id,
        'teaching_group_id' => $f['teachingGroup']->id,
        'student_id' => $student->id,
        'effective_from' => now()->subDays(30)->toDateString(),
    ]);

    return $student;
}

it('mirrors a course space 1:1 from its teaching group and refuses a duplicate (BR-ACA-08-001)', function (): void {
    $f = aca08Fixture();

    $space = app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));

    expect($space->school_id)->toBe($f['school']->id)
        ->and($space->subject_id)->toBe($f['subject']->id)
        ->and($space->term_id)->toBe($f['term']->id);

    app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));
})->throws(DuplicateCourseSpaceException::class);

it('does not auto-queue a stream-only content item into the offline cache (AC-ACA-08-001)', function (): void {
    $f = aca08Fixture();
    $space = app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));

    $item = app(CreateContentItemAction::class)->execute(new CreateContentItemData(
        courseSpaceId: $space->id, contentType: 'video', title: 'Practical Demo — Circular Motion.mp4',
        fileSizeBytes: 18_000_000, isDownloadableOffline: false,
    ));

    expect($item->file_size_bytes)->toBe(18_000_000);

    app(RecordContentDownloadAction::class)->execute(new RecordContentDownloadData(
        userId: $f['user']->id, contentItemId: $item->id,
    ));
})->throws(ContentNotDownloadableOfflineException::class);

it('records an offline download for a downloadable content item', function (): void {
    $f = aca08Fixture();
    $space = app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));
    $item = app(CreateContentItemAction::class)->execute(new CreateContentItemData(
        courseSpaceId: $space->id, contentType: 'note', title: 'Momentum Notes.pdf', fileSizeBytes: 340_000,
    ));
    app(PublishContentItemAction::class)->execute(new PublishContentItemData(contentItemId: $item->id));

    $cache = app(RecordContentDownloadAction::class)->execute(new RecordContentDownloadData(
        userId: $f['user']->id, contentItemId: $item->id, deviceId: 'device-1',
    ));

    expect($cache->downloaded_at)->not->toBeNull();
});

it('makes content inaccessible once a learner\'s teaching-group enrolment ends (AC-ACA-08-005)', function (): void {
    $f = aca08Fixture();
    $space = app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));
    $item = app(CreateContentItemAction::class)->execute(new CreateContentItemData(
        courseSpaceId: $space->id, contentType: 'note', title: 'Notes',
    ));
    app(PublishContentItemAction::class)->execute(new PublishContentItemData(contentItemId: $item->id));
    $student = aca08EnrolledStudent($f);

    $accessible = app(ListAccessibleContentAction::class)->execute(new ListAccessibleContentData(
        courseSpaceId: $space->id, studentId: $student->id,
    ));
    expect($accessible)->toHaveCount(1);

    TeachingGroupMember::where('student_id', $student->id)->update(['effective_to' => now()->subDay()->toDateString()]);

    app(ListAccessibleContentAction::class)->execute(new ListAccessibleContentData(
        courseSpaceId: $space->id, studentId: $student->id,
    ));
})->throws(LearnerNotEnrolledException::class);

it('computes final_mark as raw_mark reduced by the accept_penalised late penalty (AC-ACA-08-002)', function (): void {
    $f = aca08Fixture();
    $space = app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));
    $student = aca08EnrolledStudent($f);

    $assignment = app(CreateAssignmentAction::class)->execute(new CreateAssignmentData(
        courseSpaceId: $space->id, title: 'Essay 1', instructions: 'Write an essay.',
        opensAt: now()->subDays(10), dueAt: now()->subDays(2), latePolicy: 'accept_penalised',
        submissionType: 'text', createdByUserId: $f['user']->id, latePenaltyPercentPerDay: 10.0,
    ));
    app(PublishAssignmentAction::class)->execute(new PublishAssignmentData(assignmentId: $assignment->id));

    $submission = app(SubmitAssignmentAction::class)->execute(new SubmitAssignmentData(
        assignmentId: $assignment->id, studentId: $student->id, submittedText: 'My essay text.',
    ));

    expect($submission->is_late)->toBeTrue();

    $marked = app(MarkAssignmentSubmissionAction::class)->execute(new MarkAssignmentSubmissionData(
        submissionId: $submission->id, rawMark: 80.0, markedByUserId: $f['user']->id,
    ));

    expect((float) $marked->penalty_applied_percent)->toBe(20.0)
        ->and((float) $marked->final_mark)->toBe(64.0);
});

it('blocks a submission outright under the block late policy (BR-ACA-08-004)', function (): void {
    $f = aca08Fixture();
    $space = app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));
    $student = aca08EnrolledStudent($f);

    $assignment = app(CreateAssignmentAction::class)->execute(new CreateAssignmentData(
        courseSpaceId: $space->id, title: 'Essay 2', instructions: 'Write an essay.',
        opensAt: now()->subDays(10), dueAt: now()->subDay(), latePolicy: 'block', submissionType: 'text',
        createdByUserId: $f['user']->id,
    ));
    app(PublishAssignmentAction::class)->execute(new PublishAssignmentData(assignmentId: $assignment->id));

    app(SubmitAssignmentAction::class)->execute(new SubmitAssignmentData(
        assignmentId: $assignment->id, studentId: $student->id, submittedText: 'Too late.',
    ));
})->throws(AssignmentSubmissionBlockedException::class);

it('refuses a resubmission unless the assignment explicitly allows it (BR-ACA-08-007)', function (): void {
    $f = aca08Fixture();
    $space = app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));
    $student = aca08EnrolledStudent($f);

    $assignment = app(CreateAssignmentAction::class)->execute(new CreateAssignmentData(
        courseSpaceId: $space->id, title: 'Essay 3', instructions: 'Write an essay.',
        opensAt: now()->subDays(3), dueAt: now()->addDays(3), latePolicy: 'block', submissionType: 'text',
        createdByUserId: $f['user']->id, allowsResubmission: true,
    ));
    app(PublishAssignmentAction::class)->execute(new PublishAssignmentData(assignmentId: $assignment->id));

    $first = app(SubmitAssignmentAction::class)->execute(new SubmitAssignmentData(
        assignmentId: $assignment->id, studentId: $student->id, submittedText: 'First attempt.',
    ));
    $second = app(SubmitAssignmentAction::class)->execute(new SubmitAssignmentData(
        assignmentId: $assignment->id, studentId: $student->id, submittedText: 'Second attempt.',
    ));

    expect($first->attempt_number)->toBe(1)
        ->and($second->attempt_number)->toBe(2);
});

it('refuses a resubmission when allows_resubmission is false', function (): void {
    $f = aca08Fixture();
    $space = app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));
    $student = aca08EnrolledStudent($f);

    $assignment = app(CreateAssignmentAction::class)->execute(new CreateAssignmentData(
        courseSpaceId: $space->id, title: 'Essay 4', instructions: 'Write an essay.',
        opensAt: now()->subDays(3), dueAt: now()->addDays(3), latePolicy: 'block', submissionType: 'text',
        createdByUserId: $f['user']->id,
    ));
    app(PublishAssignmentAction::class)->execute(new PublishAssignmentData(assignmentId: $assignment->id));

    app(SubmitAssignmentAction::class)->execute(new SubmitAssignmentData(
        assignmentId: $assignment->id, studentId: $student->id, submittedText: 'Only attempt.',
    ));
    app(SubmitAssignmentAction::class)->execute(new SubmitAssignmentData(
        assignmentId: $assignment->id, studentId: $student->id, submittedText: 'Second attempt.',
    ));
})->throws(ResubmissionNotAllowedException::class);

it('flags two similar submissions in the same class for review without penalising either (AC-ACA-08-003)', function (): void {
    $f = aca08Fixture();
    $space = app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));
    $studentA = aca08EnrolledStudent($f);
    $studentB = aca08EnrolledStudent($f);

    $assignment = app(CreateAssignmentAction::class)->execute(new CreateAssignmentData(
        courseSpaceId: $space->id, title: 'Essay 5', instructions: 'Write an essay.',
        opensAt: now()->subDays(3), dueAt: now()->addDays(3), latePolicy: 'block', submissionType: 'text',
        createdByUserId: $f['user']->id,
    ));
    app(PublishAssignmentAction::class)->execute(new PublishAssignmentData(assignmentId: $assignment->id));

    $sharedText = str_repeat('The quick brown fox jumps over the lazy dog in the meadow near the river. ', 5);

    $submissionA = app(SubmitAssignmentAction::class)->execute(new SubmitAssignmentData(
        assignmentId: $assignment->id, studentId: $studentA->id, submittedText: $sharedText,
    ));
    $submissionB = app(SubmitAssignmentAction::class)->execute(new SubmitAssignmentData(
        assignmentId: $assignment->id, studentId: $studentB->id, submittedText: $sharedText,
    ));

    expect($submissionA->fresh()->similarity_flag)->toBeTrue()
        ->and($submissionB->similarity_flag)->toBeTrue()
        ->and($submissionB->raw_mark)->toBeNull()
        ->and($submissionB->final_mark)->toBeNull();
});

it('writes final marks through the normal ACA-05 mark-entry path when an assignment feeds the gradebook (AC-ACA-08-004/BR-ACA-08-008)', function (): void {
    $f = aca08Fixture();
    $space = app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));
    $student = aca08EnrolledStudent($f);
    $assessmentType = AssessmentType::factory()->for($f['school'])->create(['default_weight_percent' => 25]);

    $assignment = app(CreateAssignmentAction::class)->execute(new CreateAssignmentData(
        courseSpaceId: $space->id, title: 'Graded Coursework', instructions: 'Complete the task.',
        opensAt: now()->subDays(3), dueAt: now()->addDays(3), latePolicy: 'block', submissionType: 'file',
        createdByUserId: $f['user']->id, maxMark: 100, assessmentTypeId: $assessmentType->id,
    ));

    expect($assignment->assessment_id)->not->toBeNull();
    $assessment = Assessment::findOrFail($assignment->assessment_id);
    expect((float) $assessment->weight_percent)->toBe(25.0)
        ->and((float) $assessment->max_mark)->toBe(100.0);

    app(PublishAssignmentAction::class)->execute(new PublishAssignmentData(assignmentId: $assignment->id));
    $submission = app(SubmitAssignmentAction::class)->execute(new SubmitAssignmentData(
        assignmentId: $assignment->id, studentId: $student->id, submittedText: 'Done.',
    ));

    app(MarkAssignmentSubmissionAction::class)->execute(new MarkAssignmentSubmissionData(
        submissionId: $submission->id, rawMark: 90.0, markedByUserId: $f['user']->id,
    ));

    $mark = AssessmentMark::where('assessment_id', $assignment->assessment_id)->where('student_id', $student->id)->first();

    expect($mark)->not->toBeNull()
        ->and((float) $mark->raw_mark)->toBe(90.0);
});

it('lists non-submitters past the due date and lets a teacher chase them through CORE-09 (BR-ACA-08-009)', function (): void {
    $f = aca08Fixture();
    $space = app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));
    $submitter = aca08EnrolledStudent($f);
    $nonSubmitter = aca08EnrolledStudent($f);

    $assignment = app(CreateAssignmentAction::class)->execute(new CreateAssignmentData(
        courseSpaceId: $space->id, title: 'Essay 6', instructions: 'Write an essay.',
        opensAt: now()->subDays(10), dueAt: now()->subDay(), latePolicy: 'accept_flagged', submissionType: 'text',
        createdByUserId: $f['user']->id,
    ));
    app(PublishAssignmentAction::class)->execute(new PublishAssignmentData(assignmentId: $assignment->id));
    app(SubmitAssignmentAction::class)->execute(new SubmitAssignmentData(
        assignmentId: $assignment->id, studentId: $submitter->id, submittedText: 'Done in time... barely.',
    ));

    $nonSubmitters = app(ListNonSubmittersAction::class)->execute(new ListNonSubmittersData(assignmentId: $assignment->id));

    expect($nonSubmitters->pluck('id')->all())->toBe([$nonSubmitter->id]);

    $sent = app(ChaseNonSubmittersAction::class)->execute(new ChaseNonSubmittersData(
        assignmentId: $assignment->id, studentIds: [$nonSubmitter->id],
    ));

    expect($sent)->toBe(1);
    expect(Notification::where('notification_key', 'lms.non_submission_reminder')->where('status', 'sent')->count())->toBe(1);
});

it('hides a discussion post with a logged reason but keeps it in the database (BR-ACA-08-010)', function (): void {
    $f = aca08Fixture();
    $space = app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));
    $thread = app(CreateDiscussionThreadAction::class)->execute(new CreateDiscussionThreadData(
        courseSpaceId: $space->id, title: 'Q&A', createdByUserId: $f['user']->id,
    ));
    $student = aca08EnrolledStudent($f);
    $post = app(PostDiscussionMessageAction::class)->execute(new PostDiscussionMessageData(
        threadId: $thread->id, postedByType: 'student', postedById: $student->id, content: 'Off-topic remark.',
    ));

    $hidden = app(HideDiscussionPostAction::class)->execute(new HideDiscussionPostData(
        postId: $post->id, hiddenByUserId: $f['user']->id, reason: 'Off-topic and disrespectful.',
    ));

    expect($hidden->is_hidden)->toBeTrue()
        ->and($hidden->hidden_reason)->toBe('Off-topic and disrespectful.')
        ->and($hidden->isVisibleToLearners())->toBeFalse();

    expect(DiscussionPost::withoutGlobalScopes()->find($post->id))->not->toBeNull();
});

it('requires a reason to hide a discussion post', function (): void {
    $f = aca08Fixture();
    $space = app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData(teachingGroupId: $f['teachingGroup']->id));
    $thread = app(CreateDiscussionThreadAction::class)->execute(new CreateDiscussionThreadData(
        courseSpaceId: $space->id, title: 'Q&A', createdByUserId: $f['user']->id,
    ));
    $post = app(PostDiscussionMessageAction::class)->execute(new PostDiscussionMessageData(
        threadId: $thread->id, postedByType: 'staff', postedById: $f['user']->id, content: 'Welcome!',
    ));

    app(HideDiscussionPostAction::class)->execute(new HideDiscussionPostData(
        postId: $post->id, hiddenByUserId: $f['user']->id, reason: '   ',
    ));
})->throws(InvalidArgumentException::class);
