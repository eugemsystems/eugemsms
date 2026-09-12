<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Approvals\ApproveStepAction;
use Modules\Core\Domain\Actions\Approvals\CreateApprovalChainAction;
use Modules\Core\Domain\Actions\Approvals\RequestApprovalAction;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Approvals\ApprovalStepData;
use Modules\Core\Domain\DataObjects\Approvals\ApproveStepData;
use Modules\Core\Domain\DataObjects\Approvals\CreateApprovalChainData;
use Modules\Core\Domain\DataObjects\Approvals\RequestApprovalData;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Approvals\ChainBuilder;
use Modules\Core\Livewire\Approvals\Chains;
use Modules\Core\Livewire\Approvals\Delegations;
use Modules\Core\Livewire\Approvals\MyRequests;
use Modules\Core\Livewire\Approvals\Queue;
use Modules\Core\Livewire\Approvals\Show;
use Modules\Core\Livewire\Approvals\SlaReport;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\ApprovalChain;
use Modules\Core\Models\ApprovalDelegation;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Tests\Fixtures\TestApprovable;

function loadApprovalRoutesForTest(): void
{
    if (! Route::has('approvals.queue')) {
        require base_path('Modules/Core/routes/approvals.php');
    }
}

function grantApprovalPermissions(User $user, School $school, string ...$permissions): void
{
    loadApprovalRoutesForTest();

    $grants = array_map(function (string $permission): PermissionGrantData {
        $model = Permission::firstOrCreate(
            ['name' => $permission],
            ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'approval', 'action' => last(explode('.', $permission))],
        );

        return new PermissionGrantData($model->id, PermissionScope::School);
    }, $permissions);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: $grants,
    ));
}

function makeApprovalChainForTest(School $school, array $steps): ApprovalChain
{
    return app(CreateApprovalChainAction::class)->execute(new CreateApprovalChainData(
        schoolId: $school->id,
        approvableType: 'test_approvable',
        name: 'Chain',
        isDefault: true,
        steps: $steps,
    ));
}

beforeEach(function (): void {
    TestApprovable::resetCounters();
});

it('shows a pending request in the resolved approver\'s queue, not an uninvolved user\'s', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $bystander = User::factory()->create();
    foreach ([$requester, $approver, $bystander] as $user) {
        $user->schools()->attach($school, ['status' => 'active']);
    }
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeApprovalChainForTest($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id)]);
    app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));

    Livewire::actingAs($approver)->test(Queue::class, ['school' => $school])->assertSee('Test approvable');
    Livewire::actingAs($bystander)->test(Queue::class, ['school' => $school])->assertDontSee('Test approvable');
});

it('lets the resolved approver approve a request from the show screen', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $approver->schools()->attach($school, ['status' => 'active']);
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeApprovalChainForTest($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id)]);
    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));

    Livewire::actingAs($approver)
        ->test(Show::class, ['school' => $school, 'request' => $request])
        ->assertSee(__('Approve'))
        ->call('approve')
        ->assertDispatched('toast');

    expect($request->fresh()->status)->toBe('approved')
        ->and(TestApprovable::$approvedCount)->toBe(1);
});

it('refuses to show a request to someone with no involvement and no view-all permission', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $bystander = User::factory()->create();
    $bystander->schools()->attach($school, ['status' => 'active']);
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeApprovalChainForTest($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id)]);
    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));

    Livewire::actingAs($bystander)
        ->test(Show::class, ['school' => $school, 'request' => $request])
        ->assertForbidden();
});

it('lists the requester\'s own requests and lets them cancel a pending one', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $requester->schools()->attach($school, ['status' => 'active']);
    $approver = User::factory()->create();
    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeApprovalChainForTest($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id)]);
    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));

    Livewire::actingAs($requester)
        ->test(MyRequests::class, ['school' => $school])
        ->assertSee($request->title)
        ->call('cancel', $request->id)
        ->assertDispatched('toast');

    expect($request->fresh()->status)->toBe('cancelled');
});

it('creates an approval chain with its steps from the chain builder', function (): void {
    $school = School::factory()->create();
    $admin = User::factory()->create();
    $admin->schools()->attach($school, ['status' => 'active']);
    grantApprovalPermissions($admin, $school, 'core.approval.configure');

    Livewire::actingAs($admin)
        ->test(ChainBuilder::class, ['school' => $school])
        ->set('approvableType', 'purchase_order')
        ->set('name', 'PO Chain')
        ->set('isDefault', true)
        ->set('steps.0.name', 'Bursar review')
        ->set('steps.0.approverType', 'user')
        ->set('steps.0.approverUserId', $admin->id)
        ->call('save')
        ->assertHasNoErrors();

    $chain = ApprovalChain::where('school_id', $school->id)->where('name', 'PO Chain')->sole();
    expect($chain->steps()->count())->toBe(1);
});

it('lists chains and refuses without core.approval.configure', function (): void {
    $school = School::factory()->create();
    $admin = User::factory()->create();
    $admin->schools()->attach($school, ['status' => 'active']);

    Livewire::actingAs($admin)
        ->test(Chains::class, ['school' => $school])
        ->assertForbidden();

    grantApprovalPermissions($admin, $school, 'core.approval.configure');
    makeApprovalChainForTest($school, [new ApprovalStepData(1, 'Step', 'user', 'sequential', approverUserId: $admin->id)]);

    Livewire::actingAs($admin)
        ->test(Chains::class, ['school' => $school])
        ->assertSee('Test Approvable');
});

it('creates and revokes a delegation', function (): void {
    $school = School::factory()->create();
    $delegator = User::factory()->create();
    $delegate = User::factory()->create();
    foreach ([$delegator, $delegate] as $user) {
        $user->schools()->attach($school, ['status' => 'active']);
    }
    loadApprovalRoutesForTest();

    $component = Livewire::actingAs($delegator)
        ->test(Delegations::class, ['school' => $school])
        ->call('openCreateModal')
        ->set('delegateId', $delegate->id)
        ->set('startsAt', now()->subDay()->format('Y-m-d\TH:i'))
        ->set('endsAt', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->call('create')
        ->assertHasNoErrors();

    $delegation = ApprovalDelegation::where('delegator_id', $delegator->id)->sole();
    expect($delegation->is_active)->toBeTrue();

    $component->call('revoke', $delegation->id);
    expect($delegation->fresh()->is_active)->toBeFalse();
});

it('does not let a user revoke another user\'s delegation without core.approval.delegate_others', function (): void {
    $school = School::factory()->create();
    $delegator = User::factory()->create();
    $delegate = User::factory()->create();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($school, ['status' => 'active']);
    loadApprovalRoutesForTest();

    $delegation = ApprovalDelegation::factory()->create([
        'school_id' => $school->id,
        'delegator_id' => $delegator->id,
        'delegate_id' => $delegate->id,
    ]);

    Livewire::actingAs($outsider)
        ->test(Delegations::class, ['school' => $school])
        ->call('revoke', $delegation->id)
        ->assertForbidden();
});

it('summarises average time-to-decision by type and by approver on the SLA report', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $admin = User::factory()->create();
    $admin->schools()->attach($school, ['status' => 'active']);
    grantApprovalPermissions($admin, $school, 'core.approval.view_reports');

    $order = TestApprovable::create(['school_id' => $school->id, 'name' => 'PO-1']);
    makeApprovalChainForTest($school, [new ApprovalStepData(1, 'Bursar', 'user', 'sequential', approverUserId: $approver->id)]);
    $request = app(RequestApprovalAction::class)->execute(new RequestApprovalData($order, $school->id, $year->id, $requester->id));
    app(ApproveStepAction::class)->execute(new ApproveStepData($request->id, $approver->id));

    Livewire::actingAs($admin)
        ->test(SlaReport::class, ['school' => $school])
        ->assertSee('Test Approvable')
        ->assertSee($approver->name);
});
