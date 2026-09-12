<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Approvals\ApproveStepAction;
use Modules\Core\Domain\Actions\Approvals\CreateApprovalChainAction;
use Modules\Core\Domain\Actions\Approvals\CreateDelegationAction;
use Modules\Core\Domain\Actions\Approvals\RequestApprovalAction;
use Modules\Core\Domain\DataObjects\Approvals\ApprovalStepData;
use Modules\Core\Domain\DataObjects\Approvals\ApproveStepData;
use Modules\Core\Domain\DataObjects\Approvals\CreateApprovalChainData;
use Modules\Core\Domain\DataObjects\Approvals\CreateDelegationData;
use Modules\Core\Domain\DataObjects\Approvals\RequestApprovalData;
use Modules\Core\Domain\Support\Approvals\ApprovalEligibilityChecker;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\ApprovalChain;
use Modules\Core\Models\School;
use Modules\Core\Tests\Fixtures\TestApprovable;

function makeEligibilityChain(School $school, array $steps): ApprovalChain
{
    return app(CreateApprovalChainAction::class)->execute(new CreateApprovalChainData(
        schoolId: $school->id,
        approvableType: 'test_approvable',
        name: 'Chain',
        isDefault: true,
        steps: $steps,
    ));
}

it('says the resolved approver can act on a pending request', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeEligibilityChain($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id)]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));

    expect(app(ApprovalEligibilityChecker::class)->canActOn($request, $approver->id))->toBeTrue()
        ->and(app(ApprovalEligibilityChecker::class)->canActOn($request, $requester->id))->toBeFalse();
});

it('says an uninvolved user cannot act', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $bystander = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeEligibilityChain($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id)]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));

    expect(app(ApprovalEligibilityChecker::class)->canActOn($request, $bystander->id))->toBeFalse();
});

it('says a delegate can act on behalf of the resolved approver (BR-CORE-07-009)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $delegate = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeEligibilityChain($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id)]);

    app(CreateDelegationAction::class)->execute(new CreateDelegationData(
        schoolId: $school->id,
        delegatorId: $approver->id,
        delegateId: $delegate->id,
        startsAt: now()->subDay(),
        endsAt: now()->addDay(),
    ));

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));

    expect(app(ApprovalEligibilityChecker::class)->canActOn($request, $delegate->id))->toBeTrue();
});

it('says nobody can act on a request that is no longer pending', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeEligibilityChain($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id)]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));
    app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $approver->id));

    expect(app(ApprovalEligibilityChecker::class)->canActOn($request->fresh(), $approver->id))->toBeFalse();
});
