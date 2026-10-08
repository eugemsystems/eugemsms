<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Academic\Domain\Actions\ApproveProjectBriefAction;
use Modules\Academic\Domain\Actions\CreateAssessmentInstrumentAction;
use Modules\Academic\Domain\Actions\CreateProjectBriefAction;
use Modules\Academic\Domain\Actions\IssueProjectBriefAction;
use Modules\Academic\Domain\Actions\MarkProjectAction;
use Modules\Academic\Domain\Actions\RequestVerifiedProjectAmendmentAction;
use Modules\Academic\Domain\Actions\SubmitFinalProjectAction;
use Modules\Academic\Domain\Actions\VerifyProjectAction;
use Modules\Academic\Domain\DataObjects\AmendVerifiedProjectData;
use Modules\Academic\Domain\DataObjects\ApproveProjectBriefData;
use Modules\Academic\Domain\DataObjects\CreateAssessmentInstrumentData;
use Modules\Academic\Domain\DataObjects\CreateProjectBriefData;
use Modules\Academic\Domain\DataObjects\CriterionMarkInput;
use Modules\Academic\Domain\DataObjects\IssueProjectBriefData;
use Modules\Academic\Domain\DataObjects\MarkProjectData;
use Modules\Academic\Domain\DataObjects\SubmitFinalProjectData;
use Modules\Academic\Domain\DataObjects\VerifyProjectData;
use Modules\Academic\Livewire\Projects\Amend;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\ProjectAmendmentRequest;
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

/**
 * Book E ACA-06 §6 — `RequestVerifiedProjectAmendmentAction`/
 * `ProjectAmendmentRequest` wiring a verified project's amendment into
 * Core's real CORE-07 approvals engine, mirroring the same fix already
 * made for `Marks\Amend` (Book D ACA-05). `AmendVerifiedProjectAction`
 * used to trust a caller-asserted `approved: true` with no real
 * approval behind it; it has been removed entirely (a project has no
 * "not yet verified, amend directly" path the way a mark does, so
 * there is nothing left for it to gate). Reuses `aca06Fixture()`/
 * `aca06Student()`/`aca06Rubric()` from `Aca06ProjectsAndCalaTest`, so
 * run the module directory.
 *
 * @param  array<string, mixed>  $f
 */
function projectAmendUser(array $f, string ...$permissionNames): User
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
 */
function verifiedLearnerProject(array $f): LearnerProject
{
    $rubric = aca06Rubric($f);
    $instrument = app(CreateAssessmentInstrumentAction::class)->execute(new CreateAssessmentInstrumentData(
        schoolId: $f['school']->id, frameworkId: $f['framework']->id, code: 'SBP', name: 'School-Based Project',
    ));
    $student = aca06Student($f, 'Verified');

    LearnerSubjectEnrolment::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $student->id, 'subject_id' => $f['subject']->id, 'status' => 'active', 'added_by' => $f['user']->id,
    ]);

    $brief = app(CreateProjectBriefAction::class)->execute(new CreateProjectBriefData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, instrumentId: $instrument->id,
        subjectId: $f['subject']->id, gradeLevelId: $f['gradeLevel']->id, title: 'Water Access Project',
        description: 'Investigate local water access.', deliverables: ['Report'],
        startsOn: now(), dueOn: now()->addWeeks(6), maxMark: 100.0, rubricId: $rubric->id, createdBy: $f['user']->id,
    ));

    app(ApproveProjectBriefAction::class)->execute(new ApproveProjectBriefData(briefId: $brief->id, approvedByStaffId: $f['staff']->id));
    app(IssueProjectBriefAction::class)->execute(new IssueProjectBriefData(briefId: $brief->id));

    $learnerProject = LearnerProject::where('brief_id', $brief->id)->where('student_id', $student->id)->firstOrFail();

    app(SubmitFinalProjectAction::class)->execute(new SubmitFinalProjectData(
        learnerProjectId: $learnerProject->id, evidenceType: 'link', uploadedBy: $f['user']->id, externalUrl: 'https://example.test/evidence',
    ));

    app(MarkProjectAction::class)->execute(new MarkProjectData(
        learnerProjectId: $learnerProject->id, markerStaffId: $f['staff']->id,
        criterionMarks: [new CriterionMarkInput('Research & Planning', 20.0), new CriterionMarkInput('Execution', 40.0), new CriterionMarkInput('Presentation', 20.0)],
    ));

    app(VerifyProjectAction::class)->execute(new VerifyProjectData(learnerProjectId: $learnerProject->id, verifiedByUserId: $f['user']->id));

    return $learnerProject->fresh();
}

it('refuses to request an amendment with no approval chain configured for project_amendment', function (): void {
    $f = aca06Fixture();
    $learnerProject = verifiedLearnerProject($f);

    expect(fn () => app(RequestVerifiedProjectAmendmentAction::class)->execute(new AmendVerifiedProjectData(
        learnerProjectId: $learnerProject->id, changedByUserId: $f['user']->id,
        changeReason: 'Re-marked after a moderation review found a tallying error.',
        criterionMarks: [new CriterionMarkInput('Research & Planning', 22.0)],
    )))->toThrow(DomainException::class);
});

it('refuses to request an amendment for a project that is not verified yet', function (): void {
    $f = aca06Fixture();
    $rubric = aca06Rubric($f);
    $instrument = app(CreateAssessmentInstrumentAction::class)->execute(new CreateAssessmentInstrumentData(
        schoolId: $f['school']->id, frameworkId: $f['framework']->id, code: 'SBP', name: 'School-Based Project',
    ));
    $student = aca06Student($f, 'StillAssigned');
    LearnerSubjectEnrolment::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $student->id, 'subject_id' => $f['subject']->id, 'status' => 'active', 'added_by' => $f['user']->id,
    ]);
    $brief = app(CreateProjectBriefAction::class)->execute(new CreateProjectBriefData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, instrumentId: $instrument->id,
        subjectId: $f['subject']->id, gradeLevelId: $f['gradeLevel']->id, title: 'Unverified Project',
        description: 'x', deliverables: ['Report'], startsOn: now(), dueOn: now()->addWeeks(6),
        maxMark: 100.0, rubricId: $rubric->id, createdBy: $f['user']->id,
    ));
    app(ApproveProjectBriefAction::class)->execute(new ApproveProjectBriefData(briefId: $brief->id, approvedByStaffId: $f['staff']->id));
    app(IssueProjectBriefAction::class)->execute(new IssueProjectBriefData(briefId: $brief->id));
    $learnerProject = LearnerProject::where('brief_id', $brief->id)->where('student_id', $student->id)->firstOrFail();

    expect(fn () => app(RequestVerifiedProjectAmendmentAction::class)->execute(new AmendVerifiedProjectData(
        learnerProjectId: $learnerProject->id, changedByUserId: $f['user']->id,
        changeReason: 'Attempting to amend before verification.',
        criterionMarks: [new CriterionMarkInput('Research & Planning', 22.0)],
    )))->toThrow(InvalidStateTransitionException::class);
});

it('applies an amendment only once the request is approved, leaving the mark untouched until then', function (): void {
    $f = aca06Fixture();
    $learnerProject = verifiedLearnerProject($f);
    $approver = User::factory()->create();

    app(CreateApprovalChainAction::class)->execute(new CreateApprovalChainData(
        schoolId: $f['school']->id, approvableType: 'project_amendment', name: 'Project amendment sign-off', isDefault: true,
        steps: [new ApprovalStepData(1, 'HOD', 'user', 'sequential', approverUserId: $approver->id)],
    ));

    $pendingRequest = app(RequestVerifiedProjectAmendmentAction::class)->execute(new AmendVerifiedProjectData(
        learnerProjectId: $learnerProject->id, changedByUserId: $f['user']->id,
        changeReason: 'Re-marked after a moderation review found a tallying error.',
        criterionMarks: [new CriterionMarkInput('Research & Planning', 25.0), new CriterionMarkInput('Execution', 45.0), new CriterionMarkInput('Presentation', 25.0)],
    ));

    expect($pendingRequest->status)->toBe('pending')
        ->and((float) $learnerProject->fresh()->raw_mark)->toBe(80.0);

    app(ApproveStepAction::class)->execute(new ApproveStepData($pendingRequest->approval_request_id, $approver->id));

    expect($pendingRequest->fresh()->status)->toBe('approved')
        ->and((float) $learnerProject->fresh()->raw_mark)->toBe(95.0);
});

it('leaves the project mark untouched when the amendment request is rejected', function (): void {
    $f = aca06Fixture();
    $learnerProject = verifiedLearnerProject($f);
    $approver = User::factory()->create();

    app(CreateApprovalChainAction::class)->execute(new CreateApprovalChainData(
        schoolId: $f['school']->id, approvableType: 'project_amendment', name: 'Project amendment sign-off', isDefault: true,
        steps: [new ApprovalStepData(1, 'HOD', 'user', 'sequential', approverUserId: $approver->id)],
    ));

    $pendingRequest = app(RequestVerifiedProjectAmendmentAction::class)->execute(new AmendVerifiedProjectData(
        learnerProjectId: $learnerProject->id, changedByUserId: $f['user']->id,
        changeReason: 'Re-marked after a moderation review found a tallying error.',
        criterionMarks: [new CriterionMarkInput('Research & Planning', 25.0)],
    ));

    app(RejectStepAction::class)->execute(new RejectStepData($pendingRequest->approval_request_id, $approver->id, 'Original mark stands.'));

    expect($pendingRequest->fresh()->status)->toBe('rejected')
        ->and((float) $learnerProject->fresh()->raw_mark)->toBe(80.0);
});

it('requests approval from the Projects\\Amend screen, requiring academic.projects.amend_verified', function (): void {
    $f = aca06Fixture();
    $learnerProject = verifiedLearnerProject($f);
    $approver = User::factory()->create();

    app(CreateApprovalChainAction::class)->execute(new CreateApprovalChainData(
        schoolId: $f['school']->id, approvableType: 'project_amendment', name: 'Project amendment sign-off', isDefault: true,
        steps: [new ApprovalStepData(1, 'HOD', 'user', 'sequential', approverUserId: $approver->id)],
    ));

    $user = projectAmendUser($f, 'academic.projects.amend_verified');

    Livewire::actingAs($user)->test(Amend::class, ['school' => $f['school'], 'learnerProject' => $learnerProject->fresh()])
        ->set('changeReason', 'Re-marked after a moderation review found a tallying error.')
        ->call('amend')
        ->assertHasNoErrors();

    $pendingRequest = ProjectAmendmentRequest::where('learner_project_id', $learnerProject->id)->firstOrFail();
    expect($pendingRequest->status)->toBe('pending');

    Livewire::actingAs(projectAmendUser($f))->test(Amend::class, ['school' => $f['school'], 'learnerProject' => $learnerProject->fresh()])
        ->assertForbidden();
});
