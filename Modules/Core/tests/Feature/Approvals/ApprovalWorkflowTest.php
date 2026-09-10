<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Approvals\ApproveStepAction;
use Modules\Core\Domain\Actions\Approvals\CancelApprovalRequestAction;
use Modules\Core\Domain\Actions\Approvals\CreateApprovalChainAction;
use Modules\Core\Domain\Actions\Approvals\CreateDelegationAction;
use Modules\Core\Domain\Actions\Approvals\RejectStepAction;
use Modules\Core\Domain\Actions\Approvals\RequestApprovalAction;
use Modules\Core\Domain\Actions\Approvals\ResubmitApprovalAction;
use Modules\Core\Domain\Actions\Approvals\ReturnStepAction;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\DataObjects\Approvals\ApprovalStepData;
use Modules\Core\Domain\DataObjects\Approvals\ApproveStepData;
use Modules\Core\Domain\DataObjects\Approvals\CancelApprovalRequestData;
use Modules\Core\Domain\DataObjects\Approvals\CreateApprovalChainData;
use Modules\Core\Domain\DataObjects\Approvals\CreateDelegationData;
use Modules\Core\Domain\DataObjects\Approvals\RejectStepData;
use Modules\Core\Domain\DataObjects\Approvals\RequestApprovalData;
use Modules\Core\Domain\DataObjects\Approvals\ResubmitApprovalData;
use Modules\Core\Domain\DataObjects\Approvals\ReturnStepData;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\Exceptions\ApproverNotAuthorizedException;
use Modules\Core\Domain\Exceptions\CommentRequiredException;
use Modules\Core\Domain\Exceptions\SelfApprovalNotPermittedException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\ApprovalChain;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;
use Modules\Core\Tests\Fixtures\TestApprovable;

beforeEach(function (): void {
    TestApprovable::resetCounters();
});

function makeChain(School $school, array $steps): ApprovalChain
{
    return app(CreateApprovalChainAction::class)->execute(new CreateApprovalChainData(
        schoolId: $school->id,
        approvableType: 'test_approvable',
        name: 'Chain',
        isDefault: true,
        steps: $steps,
    ));
}

it('requests approval, selecting a chain and resolving the first step (BR-CORE-07-001/002)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);

    makeChain($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id)]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));

    expect($request->status)->toBe('pending')
        ->and($request->current_step)->toBe(1)
        ->and($request->title)->toBe("Test approvable #{$order->id}");
});

it('completes a single-step sequential chain and fires onApproved', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeChain($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id)]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));
    $approved = app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $approver->id));

    expect($approved->status)->toBe('approved')
        ->and(TestApprovable::$approvedCount)->toBe(1);
});

it('refuses self-approval regardless of role (BR-CORE-07-004/AC-CORE-07-002)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeChain($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $requester->id)]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));

    app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $requester->id));
})->throws(SelfApprovalNotPermittedException::class);

it('refuses approval from someone who is not a resolved approver', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $stranger = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeChain($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id)]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));

    app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $stranger->id));
})->throws(ApproverNotAuthorizedException::class);

it('resolves a role-based step to every holder of that role in the school (BR-CORE-07-002)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $bursar = User::factory()->create();
    $role = Role::factory()->forSchool($school->id)->create(['name' => 'bursar']);
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($bursar->id, $role->id, $school->id));

    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeChain($school, [new ApprovalStepData(1, 'Bursar', 'role', 'sequential', approverRoleId: $role->id)]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));
    $approved = app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $bursar->id));

    expect($approved->status)->toBe('approved');
});

it('advances a sequential multi-step chain one step at a time, never skipping', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $housemaster = User::factory()->create();
    $boardingMaster = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'Exeat']);
    makeChain($school, [
        new ApprovalStepData(1, 'Housemaster', 'user', 'sequential', approverUserId: $housemaster->id),
        new ApprovalStepData(2, 'Boarding Master', 'user', 'sequential', approverUserId: $boardingMaster->id),
    ]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));
    $request = app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $housemaster->id));

    expect($request->status)->toBe('pending')
        ->and($request->current_step)->toBe(2);

    $request = app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $boardingMaster->id));
    expect($request->status)->toBe('approved');
});

it('a rejection anywhere in the chain terminates it immediately without consulting later steps (BR-CORE-07-006/AC-CORE-07-003)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $housemaster = User::factory()->create();
    $boardingMaster = User::factory()->create();
    $deputyHead = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'Exeat']);
    makeChain($school, [
        new ApprovalStepData(1, 'Housemaster', 'user', 'sequential', approverUserId: $housemaster->id),
        new ApprovalStepData(2, 'Boarding Master', 'user', 'sequential', approverUserId: $boardingMaster->id),
        new ApprovalStepData(3, 'Deputy Head', 'user', 'sequential', approverUserId: $deputyHead->id),
    ]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));
    $request = app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $housemaster->id));
    $request = app(RejectStepAction::class)->execute(new RejectStepData($request->id, $boardingMaster->id));

    expect($request->status)->toBe('rejected')
        ->and(TestApprovable::$rejectedCount)->toBe(1)
        ->and($request->current_step)->toBe(2);
});

it('requires every resolved approver for parallel_all', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    $role = Role::factory()->forSchool($school->id)->create();
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($a->id, $role->id, $school->id));
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($b->id, $role->id, $school->id));
    makeChain($school, [new ApprovalStepData(1, 'Committee', 'role', 'parallel_all', approverRoleId: $role->id)]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));
    $request = app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $a->id));

    expect($request->status)->toBe('pending');

    $request = app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $b->id));
    expect($request->status)->toBe('approved');
});

it('requires only required_approvals for parallel_any', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $c = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    $role = Role::factory()->forSchool($school->id)->create();
    foreach ([$a, $b, $c] as $user) {
        app(AssignRoleAction::class)->execute(new RoleAssignmentData($user->id, $role->id, $school->id));
    }
    makeChain($school, [new ApprovalStepData(1, 'Panel', 'role', 'parallel_any', approverRoleId: $role->id, requiredApprovals: 2)]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));
    $request = app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $a->id));
    expect($request->status)->toBe('pending');

    $request = app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $b->id));
    expect($request->status)->toBe('approved');
});

it('rejects an approval with no comment when the step requires one (BR-CORE-07-014)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeChain($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id, requiresComment: true)]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));

    app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $approver->id));
})->throws(CommentRequiredException::class);

it('returns a request to the requester and resubmission restarts at step 1 (BR-CORE-07-007)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $stepOne = User::factory()->create();
    $stepTwo = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeChain($school, [
        new ApprovalStepData(1, 'Step 1', 'user', 'sequential', approverUserId: $stepOne->id),
        new ApprovalStepData(2, 'Step 2', 'user', 'sequential', approverUserId: $stepTwo->id),
    ]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));
    $request = app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $stepOne->id));
    $request = app(ReturnStepAction::class)->execute(new ReturnStepData($request->id, $stepTwo->id, 'Please fix the amount.'));

    expect($request->status)->toBe('returned')
        ->and(TestApprovable::$returnedCount)->toBe(1);

    $resubmitted = app(ResubmitApprovalAction::class)->execute(new ResubmitApprovalData($request->id));

    expect($resubmitted->status)->toBe('pending')
        ->and($resubmitted->current_step)->toBe(1);
});

it('lets the requester cancel their own pending request (BR-CORE-07-012)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeChain($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id)]);

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));
    $cancelled = app(CancelApprovalRequestAction::class)->execute(new CancelApprovalRequestData($request->id, $requester->id));

    expect($cancelled->status)->toBe('cancelled');
});

it('lets a delegate approve on behalf of the delegator, recording both identities (BR-CORE-07-009/AC-CORE-07-005)', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $head = User::factory()->create();
    $deputy = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeChain($school, [new ApprovalStepData(1, 'Head', 'user', 'sequential', approverUserId: $head->id)]);

    app(CreateDelegationAction::class)->execute(new CreateDelegationData(
        schoolId: $school->id,
        delegatorId: $head->id,
        delegateId: $deputy->id,
        startsAt: now()->subDay(),
        endsAt: now()->addDays(5),
    ));

    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));
    $approved = app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $deputy->id));

    expect($approved->status)->toBe('approved');

    $action = $approved->actions()->sole();
    expect($action->actor_id)->toBe($deputy->id)
        ->and($action->on_behalf_of_id)->toBe($head->id);
});
