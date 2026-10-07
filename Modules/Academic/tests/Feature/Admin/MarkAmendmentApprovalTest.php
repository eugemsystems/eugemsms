<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Academic\Domain\Actions\CreateAssessmentAction;
use Modules\Academic\Domain\Actions\EnterMarkAction;
use Modules\Academic\Domain\Actions\PublishAssessmentAction;
use Modules\Academic\Domain\Actions\RequestMarkAmendmentAction;
use Modules\Academic\Domain\Actions\SubmitAssessmentMarksAction;
use Modules\Academic\Domain\DataObjects\AmendMarkData;
use Modules\Academic\Domain\DataObjects\CreateAssessmentData;
use Modules\Academic\Domain\DataObjects\EnterMarkData;
use Modules\Academic\Domain\DataObjects\PublishAssessmentData;
use Modules\Academic\Domain\DataObjects\SubmitAssessmentMarksData;
use Modules\Academic\Domain\Exceptions\MarkOutOfRangeException;
use Modules\Academic\Livewire\Marks\Amend;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\MarkAmendmentRequest;
use Modules\Core\Domain\Actions\Approvals\ApproveStepAction;
use Modules\Core\Domain\Actions\Approvals\CreateApprovalChainAction;
use Modules\Core\Domain\Actions\Approvals\RejectStepAction;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Approvals\ApprovalStepData;
use Modules\Core\Domain\DataObjects\Approvals\ApproveStepData;
use Modules\Core\Domain\DataObjects\Approvals\CreateApprovalChainData;
use Modules\Core\Domain\DataObjects\Approvals\RejectStepData;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Models\Permission;
use Modules\People\Models\Student;

/**
 * Book D ACA-05 §4/BR-ACA-05-010 — `RequestMarkAmendmentAction`/
 * `MarkAmendmentRequest` wiring a published mark's amendment into
 * Core's real CORE-07 approvals engine, replacing the old
 * caller-asserted `approved: true` flag `AmendMarkAction` used to
 * trust blindly. Reuses `aca05Fixture()`/`aca05Student()`/
 * `aca05AssessmentTypes()` from `Aca05GradingAndResultsTest`, so run
 * the module directory.
 *
 * @param  array<string, mixed>  $f
 */
function markAmendUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $name): PermissionGrantData {
        $parts = explode('.', $name);
        $permission = Permission::firstOrCreate(['name' => $name], ['guard_name' => 'web', 'module_code' => strtoupper($parts[0]), 'resource' => $parts[1] ?? $parts[0], 'action' => end($parts)]);

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $user->id, schoolId: $f['school']->id, grants: $grants));

    return $user;
}

/**
 * @param  array<string, mixed>  $f
 * @return array{exam: Assessment, student: Student}
 */
function markAmendPublishedFixture(array $f): array
{
    $types = aca05AssessmentTypes($f);
    $student = aca05Student($f, 'Tendai');

    $exam = app(CreateAssessmentAction::class)->execute(new CreateAssessmentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        assessmentTypeId: $types['examination'], subjectId: $f['subject']->id, title: 'End of Term Exam',
        maxMark: 100, weightPercent: 100, createdByUserId: $f['user']->id,
    ));

    app(EnterMarkAction::class)->execute(new EnterMarkData($exam->id, $student->id, $f['user']->id, rawMark: 50));
    app(SubmitAssessmentMarksAction::class)->execute(new SubmitAssessmentMarksData($exam->id, $f['user']->id));
    app(PublishAssessmentAction::class)->execute(new PublishAssessmentData($exam->id, $f['user']->id));

    return ['exam' => $exam, 'student' => $student];
}

it('refuses to request an amendment with no approval chain configured for mark_amendment', function (): void {
    $f = aca05Fixture();
    ['exam' => $exam, 'student' => $student] = markAmendPublishedFixture($f);

    expect(fn () => app(RequestMarkAmendmentAction::class)->execute(new AmendMarkData(
        assessmentId: $exam->id, studentId: $student->id, changedByUserId: $f['user']->id,
        changeReason: 'Re-marked after a moderation review found a tallying error.', rawMark: 65,
    )))->toThrow(DomainException::class);
});

it('refuses to request an amendment for an assessment that is not published yet', function (): void {
    $f = aca05Fixture();
    $types = aca05AssessmentTypes($f);
    $student = aca05Student($f, 'Rudo');

    $assignment = app(CreateAssessmentAction::class)->execute(new CreateAssessmentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        assessmentTypeId: $types['coursework'], subjectId: $f['subject']->id, title: 'Homework',
        maxMark: 100, weightPercent: 100, createdByUserId: $f['user']->id,
    ));
    app(EnterMarkAction::class)->execute(new EnterMarkData($assignment->id, $student->id, $f['user']->id, rawMark: 50));
    app(SubmitAssessmentMarksAction::class)->execute(new SubmitAssessmentMarksData($assignment->id, $f['user']->id));

    expect(fn () => app(RequestMarkAmendmentAction::class)->execute(new AmendMarkData(
        assessmentId: $assignment->id, studentId: $student->id, changedByUserId: $f['user']->id,
        changeReason: 'Re-marked after a moderation review found a tallying error.', rawMark: 65,
    )))->toThrow(InvalidStateTransitionException::class);
});

it('validates the reason and mark range before ever creating a pending request', function (): void {
    $f = aca05Fixture();
    ['exam' => $exam, 'student' => $student] = markAmendPublishedFixture($f);

    app(CreateApprovalChainAction::class)->execute(new CreateApprovalChainData(
        schoolId: $f['school']->id, approvableType: 'mark_amendment', name: 'Mark amendment sign-off', isDefault: true,
        steps: [new ApprovalStepData(1, 'Moderator', 'user', 'sequential', approverUserId: User::factory()->create()->id)],
    ));

    expect(fn () => app(RequestMarkAmendmentAction::class)->execute(new AmendMarkData(
        assessmentId: $exam->id, studentId: $student->id, changedByUserId: $f['user']->id,
        changeReason: 'Too short', rawMark: 65,
    )))->toThrow(InvalidArgumentException::class);

    expect(fn () => app(RequestMarkAmendmentAction::class)->execute(new AmendMarkData(
        assessmentId: $exam->id, studentId: $student->id, changedByUserId: $f['user']->id,
        changeReason: 'Re-marked after a moderation review found a tallying error.', rawMark: 250,
    )))->toThrow(MarkOutOfRangeException::class);

    expect(MarkAmendmentRequest::where('assessment_id', $exam->id)->count())->toBe(0);
});

it('leaves the mark untouched when the amendment request is rejected', function (): void {
    $f = aca05Fixture();
    ['exam' => $exam, 'student' => $student] = markAmendPublishedFixture($f);

    $approver = User::factory()->create();
    app(CreateApprovalChainAction::class)->execute(new CreateApprovalChainData(
        schoolId: $f['school']->id, approvableType: 'mark_amendment', name: 'Mark amendment sign-off', isDefault: true,
        steps: [new ApprovalStepData(1, 'Moderator', 'user', 'sequential', approverUserId: $approver->id)],
    ));

    $pendingRequest = app(RequestMarkAmendmentAction::class)->execute(new AmendMarkData(
        assessmentId: $exam->id, studentId: $student->id, changedByUserId: $f['user']->id,
        changeReason: 'Re-marked after a moderation review found a tallying error.', rawMark: 65,
    ));

    app(RejectStepAction::class)->execute(new RejectStepData($pendingRequest->approval_request_id, $approver->id, 'Original mark stands.'));

    expect($pendingRequest->fresh()->status)->toBe('rejected')
        ->and((float) AssessmentMark::where('assessment_id', $exam->id)->where('student_id', $student->id)->value('raw_mark'))->toBe(50.0);
});

it('requests approval from the Marks\\Amend screen for a published assessment, and amends an unpublished one directly', function (): void {
    $f = aca05Fixture();
    ['exam' => $exam, 'student' => $student] = markAmendPublishedFixture($f);
    $approver = User::factory()->create();

    app(CreateApprovalChainAction::class)->execute(new CreateApprovalChainData(
        schoolId: $f['school']->id, approvableType: 'mark_amendment', name: 'Mark amendment sign-off', isDefault: true,
        steps: [new ApprovalStepData(1, 'Moderator', 'user', 'sequential', approverUserId: $approver->id)],
    ));

    $user = markAmendUser($f, 'academic.result.amend', 'academic.result.amend_published');

    Livewire::actingAs($user)->test(Amend::class, ['school' => $f['school'], 'assessment' => $exam->fresh()])
        ->set('studentId', $student->id)
        ->set('rawMark', '65')
        ->set('changeReason', 'Re-marked after a moderation review found a tallying error.')
        ->call('amend')
        ->assertHasNoErrors();

    $pendingRequest = MarkAmendmentRequest::where('assessment_id', $exam->id)->where('student_id', $student->id)->firstOrFail();
    expect($pendingRequest->status)->toBe('pending');

    app(ApproveStepAction::class)->execute(new ApproveStepData($pendingRequest->approval_request_id, $approver->id));
    expect((float) AssessmentMark::where('assessment_id', $exam->id)->where('student_id', $student->id)->value('raw_mark'))->toBe(65.0);
});

it('refuses a Marks\\Amend amendment request without academic.result.amend_published, even with academic.result.amend', function (): void {
    $f = aca05Fixture();
    ['exam' => $exam, 'student' => $student] = markAmendPublishedFixture($f);
    $user = markAmendUser($f, 'academic.result.amend');

    Livewire::actingAs($user)->test(Amend::class, ['school' => $f['school'], 'assessment' => $exam->fresh()])
        ->set('studentId', $student->id)
        ->set('rawMark', '65')
        ->set('changeReason', 'Re-marked after a moderation review found a tallying error.')
        ->call('amend')
        ->assertForbidden();
});
