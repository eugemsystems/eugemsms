<?php

use Livewire\Livewire;
use Modules\Academic\Domain\Actions\ApproveProjectBriefAction;
use Modules\Academic\Domain\Actions\CreateAssessmentInstrumentAction;
use Modules\Academic\Domain\Actions\CreateProjectBriefAction;
use Modules\Academic\Domain\Actions\GenerateProjectNationalSubmissionExportAction;
use Modules\Academic\Domain\Actions\IssueProjectBriefAction;
use Modules\Academic\Domain\Actions\SubmitFinalProjectAction;
use Modules\Academic\Domain\Actions\ValidateProjectNationalSubmissionAction;
use Modules\Academic\Domain\DataObjects\ApproveProjectBriefData;
use Modules\Academic\Domain\DataObjects\CreateAssessmentInstrumentData;
use Modules\Academic\Domain\DataObjects\CreateProjectBriefData;
use Modules\Academic\Domain\DataObjects\GenerateProjectNationalSubmissionData;
use Modules\Academic\Domain\DataObjects\IssueProjectBriefData;
use Modules\Academic\Domain\DataObjects\SubmitFinalProjectData;
use Modules\Academic\Domain\DataObjects\ValidateProjectNationalSubmissionData;
use Modules\Academic\Domain\Exceptions\ProjectSubmissionExportBlockedException;
use Modules\Academic\Livewire\Projects\Export;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectNationalSubmission;
use Modules\Core\Models\Document;

/**
 * Book E ACA-06 §6/BR-ACA-06-018 — the national submission export.
 * Reuses `aca06Fixture()`/`aca06Rubric()`/`aca06Student()` from
 * `Aca06ProjectsAndCalaTest` and `verifiedLearnerProject()`/
 * `projectAmendUser()` from `ProjectAmendmentApprovalTest`, so run the
 * module directory.
 */
it('runs a clean validation report and exports once every candidate is verified', function (): void {
    $f = aca06Fixture();
    $learnerProject = verifiedLearnerProject($f);
    $instrumentId = ProjectBrief::findOrFail($learnerProject->brief_id)->instrument_id;

    $issues = app(ValidateProjectNationalSubmissionAction::class)->execute(new ValidateProjectNationalSubmissionData(
        instrumentId: $instrumentId, academicYearId: $f['year']->id,
    ));

    // The fixture's subject has no grading scale, so the project has no grade — a warning,
    // which does not block export, not an error.
    expect($issues->where('severity', 'error'))->toHaveCount(0)
        ->and($issues->where('severity', 'warning')->pluck('field')->all())->toBe(['grade']);

    $submission = app(GenerateProjectNationalSubmissionExportAction::class)->execute(new GenerateProjectNationalSubmissionData(
        instrumentId: $instrumentId, academicYearId: $f['year']->id, generatedByUserId: $f['user']->id,
    ));

    expect($submission)->toBeInstanceOf(ProjectNationalSubmission::class)
        ->and($submission->candidate_count)->toBe(1)
        ->and($submission->document_id)->not->toBeNull();

    $document = Document::findOrFail($submission->document_id);
    expect($document->document_type)->toBe('project_national_submission');
});

it('refuses to export while a candidate is not yet verified, naming the error', function (): void {
    $f = aca06Fixture();
    $rubric = aca06Rubric($f);
    $instrument = app(CreateAssessmentInstrumentAction::class)->execute(new CreateAssessmentInstrumentData(
        schoolId: $f['school']->id, frameworkId: $f['framework']->id, code: 'SBP2', name: 'School-Based Project',
    ));
    $student = aca06Student($f, 'NotYetVerified');

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
    app(SubmitFinalProjectAction::class)->execute(new SubmitFinalProjectData(
        learnerProjectId: $learnerProject->id, evidenceType: 'link', uploadedBy: $f['user']->id, externalUrl: 'https://example.test/evidence',
    ));

    $issues = app(ValidateProjectNationalSubmissionAction::class)->execute(new ValidateProjectNationalSubmissionData(
        instrumentId: $instrument->id, academicYearId: $f['year']->id,
    ));

    expect($issues->where('severity', 'error'))->toHaveCount(1);

    expect(fn () => app(GenerateProjectNationalSubmissionExportAction::class)->execute(new GenerateProjectNationalSubmissionData(
        instrumentId: $instrument->id, academicYearId: $f['year']->id, generatedByUserId: $f['user']->id,
    )))->toThrow(ProjectSubmissionExportBlockedException::class);

    expect(ProjectNationalSubmission::where('instrument_id', $instrument->id)->count())->toBe(0);
});

it('runs the validation report and export from the Projects\\Export screen, requiring academic.projects.export', function (): void {
    $f = aca06Fixture();
    $learnerProject = verifiedLearnerProject($f);
    $instrumentId = ProjectBrief::findOrFail($learnerProject->brief_id)->instrument_id;

    $user = projectAmendUser($f, 'academic.projects.export');

    Livewire::actingAs($user)->test(Export::class, ['school' => $f['school']])
        ->set('instrumentId', $instrumentId)
        ->set('academicYearId', $f['year']->id)
        ->call('validateSubmission')
        ->call('export')
        ->assertHasNoErrors();

    expect(ProjectNationalSubmission::where('instrument_id', $instrumentId)->count())->toBe(1);

    Livewire::actingAs(projectAmendUser($f))->test(Export::class, ['school' => $f['school']])->assertForbidden();
});
