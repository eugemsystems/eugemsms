<?php

use App\Models\User;
use Carbon\CarbonInterface;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Actions\Approvals\ApproveStepAction;
use Modules\Core\Domain\Actions\Approvals\CreateApprovalChainAction;
use Modules\Core\Domain\DataObjects\Approvals\ApprovalStepData;
use Modules\Core\Domain\DataObjects\Approvals\ApproveStepData;
use Modules\Core\Domain\DataObjects\Approvals\CreateApprovalChainData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\Actions\ApproveBillingRunAction;
use Modules\Finance\Domain\Actions\ComputeBillingRunAction;
use Modules\Finance\Domain\Actions\CreateBudgetEnvelopeAction;
use Modules\Finance\Domain\Actions\CreateDiscountSchemeAction;
use Modules\Finance\Domain\Actions\DecideScholarshipApplicationAction;
use Modules\Finance\Domain\Actions\GenerateCostOfGenerosityReportAction;
use Modules\Finance\Domain\Actions\GrantAwardAction;
use Modules\Finance\Domain\Actions\IssueInvoicesForAssignmentAction;
use Modules\Finance\Domain\Actions\ReviewAwardConditionAction;
use Modules\Finance\Domain\Actions\SubmitScholarshipApplicationAction;
use Modules\Finance\Domain\DataObjects\ApproveBillingRunData;
use Modules\Finance\Domain\DataObjects\ComputeBillingRunData;
use Modules\Finance\Domain\DataObjects\CreateBudgetEnvelopeData;
use Modules\Finance\Domain\DataObjects\CreateDiscountSchemeData;
use Modules\Finance\Domain\DataObjects\DecideScholarshipApplicationData;
use Modules\Finance\Domain\DataObjects\GrantAwardData;
use Modules\Finance\Domain\DataObjects\IssueInvoicesForAssignmentData;
use Modules\Finance\Domain\DataObjects\SubmitScholarshipApplicationData;
use Modules\Finance\Domain\Exceptions\ApplicationNotApprovedException;
use Modules\Finance\Domain\Exceptions\ApplicationRequiredException;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AwardUtilisation;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\People\Domain\Actions\ChangeStudentStatusAction;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\Actions\LinkGuardianToStudentAction;
use Modules\People\Domain\Actions\WithdrawStudentAction;
use Modules\People\Domain\DataObjects\ChangeStudentStatusData;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Domain\DataObjects\LinkGuardianToStudentData;
use Modules\People\Domain\DataObjects\WithdrawStudentData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;

/**
 * @param  array<string, mixed>  $f
 */
function fin07Sibling(array $f, CarbonInterface $dateOfBirth, ?Guardian $guardian = null, string $firstName = 'Sibling'): array
{
    $student = app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        firstName: $firstName, lastName: 'Household', dateOfBirth: $dateOfBirth, gender: 'female',
        enrolmentType: 'FULL_TIME', residency: 'DAY', sectionId: $f['section']->id, gradeLevelId: $f['gradeLevel']->id,
        entryCohortYear: (int) now()->year, createdByUserId: $f['user']->id, skipDuplicateCheck: true,
    ));

    $guardian ??= Guardian::factory()->for($f['school'])->create();

    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
        studentId: $student->id, guardianId: $guardian->id, relationship: 'mother',
        createdByUserId: $f['user']->id, isFeeResponsible: true,
    ));

    return [$student, $guardian];
}

it('applies the sibling discount tier automatically, live, with no manual award (AC-FIN-07-001/BR-FIN-07-002/003)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f, 40000);

    $guardian = Guardian::factory()->for($f['school'])->create();
    [$elder] = fin07Sibling($f, now()->subYears(17), $guardian, 'Elder');
    [$younger] = fin07Sibling($f, now()->subYears(14), $guardian, 'Younger');

    app(CreateDiscountSchemeAction::class)->execute(new CreateDiscountSchemeData(
        schoolId: $f['school']->id, code: 'SIBLING', name: 'Sibling Discount', schemeType: 'automatic',
        category: 'sibling', calculationMethod: 'percentage', contraAccountId: Account::factory()->for($f['school'])->create()->id,
        requiresApproval: false,
        tierBands: [['nth' => 2, 'percent' => '10.00'], ['nth' => 3, 'percent' => '15.00']],
    ));

    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
        $f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$elder->id, $younger->id],
    ));

    $elderAssignment = LearnerFeeAssignment::where('student_id', $elder->id)->where('billing_run_id', $run->id)->first();
    $youngerAssignment = LearnerFeeAssignment::where('student_id', $younger->id)->where('billing_run_id', $run->id)->first();

    expect((int) $elderAssignment->lines()->sum('discount_minor'))->toBe(0)
        ->and((int) $youngerAssignment->lines()->sum('discount_minor'))->toBe(4000)
        ->and((int) $youngerAssignment->lines()->sum('net_minor'))->toBe(36000);
});

it('ends the staff-child discount per the configured notice period after the staff members exit, not immediately or indefinitely (AC-FIN-07-002/BR-FIN-07-004)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f, 40000);

    $staffUser = User::factory()->create();
    $guardian = Guardian::factory()->for($f['school'])->create(['user_id' => $staffUser->id]);
    Staff::factory()->for($f['school'])->create(['user_id' => $staffUser->id, 'status' => 'active', 'exited_on' => null]);
    [$child] = fin07Sibling($f, now()->subYears(14), $guardian, 'StaffChild');

    app(CreateDiscountSchemeAction::class)->execute(new CreateDiscountSchemeData(
        schoolId: $f['school']->id, code: 'STAFFCHILD', name: 'Staff Child Discount', schemeType: 'automatic',
        category: 'staff', calculationMethod: 'percentage', contraAccountId: Account::factory()->for($f['school'])->create()->id,
        requiresApproval: false, defaultPercent: '50.00',
    ));

    $activeRun = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
        $f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$child->id],
    ));
    $activeAssignment = LearnerFeeAssignment::where('student_id', $child->id)->where('billing_run_id', $activeRun->id)->first();
    expect((int) $activeAssignment->lines()->sum('discount_minor'))->toBe(20000);

    Staff::where('user_id', $staffUser->id)->first()->update(['status' => 'exited', 'exited_on' => now()->subDays(60)]);

    $afterExitRun = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
        $f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$child->id],
    ));
    $afterExitAssignment = LearnerFeeAssignment::where('student_id', $child->id)->where('billing_run_id', $afterExitRun->id)->first();
    expect((int) $afterExitAssignment->lines()->sum('discount_minor'))->toBe(0);
});

it('refuses an award that would exceed its capped budget envelope, naming the shortfall (AC-FIN-07-003/BR-FIN-07-009)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f, 100000);
    $studentA = fin02Student($f, ['firstName' => 'First']);
    $studentB = fin02Student($f, ['firstName' => 'Second']);

    // percentage, not fixed_amount: finance.award_approval_threshold_minor
    // defaults to 0 ("all fixed-amount awards need approval"), which
    // would otherwise route these through CORE-07 — a real, separate
    // concern this test isn't exercising.
    $scheme = app(CreateDiscountSchemeAction::class)->execute(new CreateDiscountSchemeData(
        schoolId: $f['school']->id, code: 'ACADEMIC', name: 'Academic Scholarship', schemeType: 'individually_granted',
        category: 'academic', calculationMethod: 'percentage', contraAccountId: Account::factory()->for($f['school'])->create()->id,
        requiresApproval: false,
    ));

    app(CreateBudgetEnvelopeAction::class)->execute(new CreateBudgetEnvelopeData(
        schoolId: $f['school']->id, schemeId: $scheme->id, academicYearId: $f['year']->id, budgetMinor: 19500, currency: 'USD',
    ));

    app(GrantAwardAction::class)->execute(new GrantAwardData(
        schoolId: $f['school']->id, schemeId: $scheme->id, studentId: $studentA->id, academicYearId: $f['year']->id,
        grantedByUserId: $f['user']->id, awardMethod: 'percentage', effectiveFrom: now(), awardPercent: '19.50', currency: 'USD',
    ));
    app(GrantAwardAction::class)->execute(new GrantAwardData(
        schoolId: $f['school']->id, schemeId: $scheme->id, studentId: $studentB->id, academicYearId: $f['year']->id,
        grantedByUserId: $f['user']->id, awardMethod: 'percentage', effectiveFrom: now(), awardPercent: '1.00', currency: 'USD',
    ));

    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
        $f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$studentA->id, $studentB->id],
    ));

    $assignmentA = LearnerFeeAssignment::where('student_id', $studentA->id)->where('billing_run_id', $run->id)->first();
    $assignmentB = LearnerFeeAssignment::where('student_id', $studentB->id)->where('billing_run_id', $run->id)->first();

    expect((int) $assignmentA->lines()->sum('discount_minor'))->toBe(19500)
        ->and((int) $assignmentB->lines()->sum('discount_minor'))->toBe(0);
});

it('invoices the sponsor the full amount and posts no discount when an award is sponsor-funded (AC-FIN-07-004/BR-FIN-07-010)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f, 60000);
    $student = fin02Student($f);
    $sponsor = Guardian::factory()->for($f['school'])->organisation()->create();

    $scheme = app(CreateDiscountSchemeAction::class)->execute(new CreateDiscountSchemeData(
        schoolId: $f['school']->id, code: 'CORP', name: 'Corporate Sponsorship', schemeType: 'individually_granted',
        category: 'corporate', calculationMethod: 'percentage', contraAccountId: Account::factory()->for($f['school'])->create()->id,
        requiresApproval: false, isSponsorFunded: true,
    ));

    app(GrantAwardAction::class)->execute(new GrantAwardData(
        schoolId: $f['school']->id, schemeId: $scheme->id, studentId: $student->id, academicYearId: $f['year']->id,
        grantedByUserId: $f['user']->id, awardMethod: 'percentage', effectiveFrom: now(), awardPercent: '100.00',
        currency: 'USD', sponsorGuardianId: $sponsor->id,
    ));

    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
        $f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id],
    ));
    app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($run->id, $f['user']->id));

    $assignment = LearnerFeeAssignment::where('student_id', $student->id)->where('billing_run_id', $run->id)->first();

    expect((int) $assignment->lines()->sum('discount_minor'))->toBe(0)
        ->and((int) $assignment->lines()->sum('gross_minor'))->toBe(60000);

    $invoices = app(IssueInvoicesForAssignmentAction::class)->execute(new IssueInvoicesForAssignmentData($assignment->id, $f['user']->id));

    expect($invoices)->toHaveCount(1)
        ->and($invoices->first()->billed_party_id)->toBe($sponsor->id)
        ->and((int) $invoices->first()->net_minor)->toBe(60000)
        ->and((int) $invoices->first()->discount_minor)->toBe(0);
});

it('suspends, never auto-revokes, an academic award whose condition is not met at review (AC-FIN-07-005/BR-FIN-07-007/011)', function (): void {
    $f = fin02Fixture();
    $student = fin02Student($f);

    $scheme = app(CreateDiscountSchemeAction::class)->execute(new CreateDiscountSchemeData(
        schoolId: $f['school']->id, code: 'MERIT', name: 'Merit Scholarship', schemeType: 'individually_granted',
        category: 'academic', calculationMethod: 'percentage', contraAccountId: Account::factory()->for($f['school'])->create()->id,
        requiresApproval: false, requiresAcademicThreshold: true, minimumAveragePercent: '60.00',
    ));

    $award = app(GrantAwardAction::class)->execute(new GrantAwardData(
        schoolId: $f['school']->id, schemeId: $scheme->id, studentId: $student->id, academicYearId: $f['year']->id,
        grantedByUserId: $f['user']->id, awardMethod: 'percentage', effectiveFrom: now(), awardPercent: '25.00', currency: 'USD',
    ));

    TermResult::factory()->create(['school_id' => $f['school']->id, 'student_id' => $student->id, 'term_id' => $f['term']->id, 'average_percent' => '54.00']);

    $reviewed = app(ReviewAwardConditionAction::class)->execute($award->id, $f['term']->id);

    expect($reviewed->condition_met)->toBeFalse()
        ->and($reviewed->status)->toBe('suspended');
});

it('shows gross billed, discount granted, and net billed separately per scheme, leaving gross income unaffected (AC-FIN-07-006/BR-FIN-07-014)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f, 50000);
    $student = fin02Student($f);

    $scheme = app(CreateDiscountSchemeAction::class)->execute(new CreateDiscountSchemeData(
        schoolId: $f['school']->id, code: 'HARDSHIP', name: 'Hardship Bursary', schemeType: 'individually_granted',
        category: 'hardship', calculationMethod: 'percentage', contraAccountId: Account::factory()->for($f['school'])->create()->id,
        requiresApproval: false,
    ));

    app(GrantAwardAction::class)->execute(new GrantAwardData(
        schoolId: $f['school']->id, schemeId: $scheme->id, studentId: $student->id, academicYearId: $f['year']->id,
        grantedByUserId: $f['user']->id, awardMethod: 'percentage', effectiveFrom: now(), awardPercent: '20.00', currency: 'USD',
    ));

    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
        $f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id],
    ));
    app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($run->id, $f['user']->id));
    $assignment = LearnerFeeAssignment::where('student_id', $student->id)->where('billing_run_id', $run->id)->first();
    app(IssueInvoicesForAssignmentAction::class)->execute(new IssueInvoicesForAssignmentData($assignment->id, $f['user']->id));

    $report = app(GenerateCostOfGenerosityReportAction::class)->execute($f['school']->id, $f['term']->id);

    expect($report)->toHaveCount(1);
    $summary = $report[0];
    expect($summary->schemeCode)->toBe('HARDSHIP')
        ->and($summary->grossMinor)->toBe(50000)
        ->and($summary->discountMinor)->toBe(10000)
        ->and($summary->netMinor)->toBe(40000);
});

it('ends an active award on the learners exit date with no retroactive re-invoicing of already-billed terms (AC-FIN-07-007/BR-FIN-07-012/015)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f, 30000);
    $student = fin02Student($f);

    $scheme = app(CreateDiscountSchemeAction::class)->execute(new CreateDiscountSchemeData(
        schoolId: $f['school']->id, code: 'BURSARY', name: 'General Bursary', schemeType: 'individually_granted',
        category: 'hardship', calculationMethod: 'percentage', contraAccountId: Account::factory()->for($f['school'])->create()->id,
        requiresApproval: false,
    ));

    $award = app(GrantAwardAction::class)->execute(new GrantAwardData(
        schoolId: $f['school']->id, schemeId: $scheme->id, studentId: $student->id, academicYearId: $f['year']->id,
        grantedByUserId: $f['user']->id, awardMethod: 'percentage', effectiveFrom: now(), awardPercent: '15.00', currency: 'USD',
    ));

    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
        $f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id],
    ));
    app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($run->id, $f['user']->id));
    $assignment = LearnerFeeAssignment::where('student_id', $student->id)->where('billing_run_id', $run->id)->first();
    $preWithdrawalLineDiscount = (int) $assignment->lines()->sum('discount_minor');

    // A freshly-created student starts at 'enrolled' (PPL-01's own
    // entry state) — it must be 'active' before it can withdraw.
    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData(
        studentId: $student->id, newStatus: 'active', changedByUserId: $f['user']->id,
    ));

    app(WithdrawStudentAction::class)->execute(new WithdrawStudentData(
        studentId: $student->id, withdrawnByUserId: $f['user']->id, exitedOn: now(), reason: 'Family relocation',
    ));

    expect($award->fresh()->status)->toBe('ended')
        ->and($preWithdrawalLineDiscount)->toBe(4500)
        ->and((int) $assignment->fresh()->lines()->sum('discount_minor'))->toBe(4500);
});

it('refuses a standalone award for an application-based scheme with no approved application (BR-FIN-07-005)', function (): void {
    $f = fin02Fixture();
    $student = fin02Student($f);

    $scheme = app(CreateDiscountSchemeAction::class)->execute(new CreateDiscountSchemeData(
        schoolId: $f['school']->id, code: 'NEEDS', name: 'Needs-Based Scholarship', schemeType: 'application_based',
        category: 'hardship', calculationMethod: 'percentage', contraAccountId: Account::factory()->for($f['school'])->create()->id,
        requiresApproval: false,
    ));

    expect(fn () => app(GrantAwardAction::class)->execute(new GrantAwardData(
        schoolId: $f['school']->id, schemeId: $scheme->id, studentId: $student->id, academicYearId: $f['year']->id,
        grantedByUserId: $f['user']->id, awardMethod: 'percentage', effectiveFrom: now(), awardPercent: '30.00', currency: 'USD',
    )))->toThrow(ApplicationRequiredException::class);

    $application = app(SubmitScholarshipApplicationAction::class)->execute(new SubmitScholarshipApplicationData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, schemeId: $scheme->id, studentId: $student->id,
        narrative: 'Household income has dropped sharply this year.',
    ));

    expect(fn () => app(GrantAwardAction::class)->execute(new GrantAwardData(
        schoolId: $f['school']->id, schemeId: $scheme->id, studentId: $student->id, academicYearId: $f['year']->id,
        grantedByUserId: $f['user']->id, awardMethod: 'percentage', effectiveFrom: now(), awardPercent: '30.00',
        currency: 'USD', applicationId: $application->id,
    )))->toThrow(ApplicationNotApprovedException::class);

    app(DecideScholarshipApplicationAction::class)->execute(new DecideScholarshipApplicationData(
        applicationId: $application->id, status: 'approved', decidedByUserId: $f['user']->id, committeeNotes: 'Approved unanimously.',
    ));

    $award = app(GrantAwardAction::class)->execute(new GrantAwardData(
        schoolId: $f['school']->id, schemeId: $scheme->id, studentId: $student->id, academicYearId: $f['year']->id,
        grantedByUserId: $f['user']->id, awardMethod: 'percentage', effectiveFrom: now(), awardPercent: '30.00',
        currency: 'USD', applicationId: $application->id,
    ));

    expect($award->status)->toBe('active');
});

it('routes an award requiring approval through CORE-07 and only activates it once approved', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f, 20000);
    $student = fin02Student($f);
    $approver = User::factory()->create();

    app(CreateApprovalChainAction::class)->execute(new CreateApprovalChainData(
        schoolId: $f['school']->id, approvableType: 'discount_award', name: 'Award Approval', isDefault: true,
        createdByUserId: $f['user']->id,
        steps: [new ApprovalStepData(stepNumber: 1, name: 'Bursar sign-off', approverType: 'user', mode: 'parallel_any', approverUserId: $approver->id)],
    ));

    $scheme = app(CreateDiscountSchemeAction::class)->execute(new CreateDiscountSchemeData(
        schoolId: $f['school']->id, code: 'BIGAWARD', name: 'Large Discretionary Award', schemeType: 'individually_granted',
        category: 'hardship', calculationMethod: 'percentage', contraAccountId: Account::factory()->for($f['school'])->create()->id,
        requiresApproval: true,
    ));

    $award = app(GrantAwardAction::class)->execute(new GrantAwardData(
        schoolId: $f['school']->id, schemeId: $scheme->id, studentId: $student->id, academicYearId: $f['year']->id,
        grantedByUserId: $f['user']->id, awardMethod: 'percentage', effectiveFrom: now(), awardPercent: '50.00', currency: 'USD',
    ));

    expect($award->status)->toBe('pending_approval')
        ->and($award->approval_request_id)->not->toBeNull();

    $runBefore = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
        $f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id],
    ));
    $assignmentBefore = LearnerFeeAssignment::where('student_id', $student->id)->where('billing_run_id', $runBefore->id)->first();
    expect((int) $assignmentBefore->lines()->sum('discount_minor'))->toBe(0);

    app(ApproveStepAction::class)->execute(new ApproveStepData(
        requestId: $award->approval_request_id, actorUserId: $approver->id,
    ));

    expect($award->fresh()->status)->toBe('active');
});

it('refuses to update or delete an append-only award_utilisation row', function (): void {
    fin02Fixture();
    $utilisation = AwardUtilisation::factory()->create();

    expect(fn () => $utilisation->update(['discount_minor' => 1]))->toThrow(InvalidStateTransitionException::class);
    expect(fn () => $utilisation->delete())->toThrow(InvalidStateTransitionException::class);
});
