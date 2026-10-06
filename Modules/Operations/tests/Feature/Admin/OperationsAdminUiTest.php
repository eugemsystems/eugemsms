<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\CostCentre;
use Modules\Operations\Livewire\Maintenance\Assets\Index as AssetsIndex;
use Modules\Operations\Livewire\Maintenance\Report as MaintenanceReport;
use Modules\Operations\Livewire\Maintenance\Reports\Index as ReportsIndex;
use Modules\Operations\Livewire\Maintenance\Schedules\Index as SchedulesIndex;
use Modules\Operations\Livewire\Maintenance\Triage as MaintenanceTriage;
use Modules\Operations\Livewire\Maintenance\WorkOrders\Index as WorkOrdersIndex;
use Modules\Operations\Livewire\Projects\Index as ProjectsIndex;
use Modules\Operations\Models\CapitalProject;
use Modules\Operations\Models\CapitalProjectMilestone;
use Modules\Operations\Models\MaintenanceAsset;
use Modules\Operations\Models\MaintenanceSchedule;
use Modules\Operations\Models\WorkOrder;
use Modules\Stores\Models\Supplier;

/**
 * Book H2 OPS-02 admin-UI pass. Own, distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, user: User, costCentre: CostCentre}
 */
function operationsAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create(['financial_state' => 'open']);
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'fault_report', pattern: 'FLT/{SEQ:6}'));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'work_order', pattern: 'WO/{SEQ:6}', academicYearId: $year->id));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'capital_project', pattern: 'CAP/{SEQ:6}'));

    $costCentre = CostCentre::factory()->for($school)->create();

    return compact('school', 'year', 'term', 'user', 'costCentre');
}

/**
 * Every permission this module registers is a two-segment
 * `module.action` shape — splits on the last dot for the action, the
 * same parser `.ai/rules/stores.md` documents for its own mixed
 * permission shapes.
 *
 * @param  array<string, mixed>  $f
 */
function operationsAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $lastDot = strrpos($permissionName, '.');
        $moduleCode = strtoupper(substr($permissionName, 0, strpos($permissionName, '.')));
        $action = substr($permissionName, $lastDot + 1);
        $resource = substr($permissionName, strpos($permissionName, '.') + 1, $lastDot - strpos($permissionName, '.') - 1);
        $resource = $resource !== '' ? $resource : $action;

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => $moduleCode, 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

it('refuses to mount the triage queue for a user with no maintenance.triage grant', function (): void {
    $f = operationsAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(MaintenanceTriage::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every maintenance and projects screen for a fully-permissioned user', function (): void {
    $f = operationsAdminFixture();
    $user = operationsAdminUser($f, 'maintenance.view', 'maintenance.manage', 'maintenance.triage', 'maintenance.execute', 'maintenance.project.manage', 'maintenance.report.view');

    Livewire::actingAs($user)->test(MaintenanceReport::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(MaintenanceTriage::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(WorkOrdersIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(AssetsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(SchedulesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ReportsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ProjectsIndex::class, ['school' => $f['school']])->assertOk();
});

it('jumps a safety-affecting fault report to the top of the triage queue regardless of severity (AC-OPS-02-001)', function (): void {
    $f = operationsAdminFixture();
    $reporter = operationsAdminUser($f);
    $triager = operationsAdminUser($f, 'maintenance.triage');

    // A routine, non-safety report reported first.
    Livewire::actingAs($reporter)->test(MaintenanceReport::class, ['school' => $f['school']])
        ->set('location', 'Block A corridor')
        ->set('description', 'Flickering light')
        ->set('severity', 'cosmetic')
        ->call('report')->assertOk();

    // A cosmetic-severity report reported second, but flagged affects_safety.
    Livewire::actingAs($reporter)->test(MaintenanceReport::class, ['school' => $f['school']])
        ->set('location', 'Science lab')
        ->set('description', 'Exposed live wire near a bench')
        ->set('severity', 'cosmetic')
        ->set('affectsSafety', true)
        ->call('report')->assertOk();

    $component = Livewire::actingAs($triager)->test(MaintenanceTriage::class, ['school' => $f['school']]);
    $reports = $component->viewData('reports');

    expect($reports->first()->affects_safety)->toBeTrue();
    expect($reports->first()->location)->toBe('Science lab');
});

it('generates a preventive work order through the Schedules screen when a calendar schedule falls due (AC-OPS-02-003)', function (): void {
    $f = operationsAdminFixture();
    $manager = operationsAdminUser($f, 'maintenance.manage');

    $asset = MaintenanceAsset::factory()->for($f['school'])->create(['cost_centre_id' => $f['costCentre']->id, 'criticality' => 'critical']);

    Livewire::actingAs($manager)->test(SchedulesIndex::class, ['school' => $f['school']])
        ->set('maintenanceAssetId', $asset->id)
        ->set('name', 'Generator 250-hour service')
        ->set('triggerType', 'calendar')
        ->set('intervalDays', 30)
        ->set('leadTimeDays', 7)
        ->set('taskChecklistText', "Check oil\nCheck filters")
        ->call('create')->assertOk();

    $schedule = MaintenanceSchedule::where('school_id', $f['school']->id)->firstOrFail();
    // Force it due today regardless of the 30-day interval just created.
    $schedule->update(['next_due_on' => now()->toDateString()]);

    expect(WorkOrder::where('school_id', $f['school']->id)->count())->toBe(0);

    Livewire::actingAs($manager)->test(SchedulesIndex::class, ['school' => $f['school']])
        ->call('generateDue')->assertOk();

    $workOrder = WorkOrder::where('school_id', $f['school']->id)->where('schedule_id', $schedule->id)->first();

    expect($workOrder)->not->toBeNull();
    expect($workOrder->work_type)->toBe('preventive');
});

it('adds milestones within the 100% payment cap, completes them only while in progress, and assigns an active contractor (OPS-02)', function (): void {
    $f = operationsAdminFixture();
    $user = operationsAdminUser($f, 'maintenance.project.manage');
    $project = CapitalProject::factory()->for($f['school'])->create(['status' => 'approved', 'starts_on' => now()->toDateString()]);
    $active = Supplier::factory()->for($f['school'])->create(['status' => 'active']);
    $pending = Supplier::factory()->for($f['school'])->create(['status' => 'pending_approval']);

    $screen = fn () => Livewire::actingAs($user)->test(ProjectsIndex::class, ['school' => $f['school']])->call('select', $project->id);

    $screen()->set('milestoneName', 'Foundations')->set('milestoneTargetDate', now()->addMonth()->toDateString())->set('milestonePaymentPercent', '60')->call('addMilestone')->assertHasNoErrors();
    $screen()->set('milestoneName', 'Roof')->set('milestoneTargetDate', now()->addMonths(2)->toDateString())->set('milestonePaymentPercent', '50')->call('addMilestone')->assertHasErrors(['paymentPercent']);
    $screen()->set('milestoneName', 'Too early')->set('milestoneTargetDate', now()->subYear()->toDateString())->call('addMilestone')->assertHasErrors(['targetDate']);

    $milestone = CapitalProjectMilestone::where('project_id', $project->id)->firstOrFail();
    expect($milestone->sequence)->toBe(1);

    $screen()->call('completeMilestone', $milestone->id);
    expect($milestone->fresh()->status)->toBe('pending');

    $project->update(['status' => 'in_progress']);
    $screen()->call('completeMilestone', $milestone->id);
    expect($milestone->fresh()->status)->toBe('completed');

    $screen()->set('contractorId', $pending->id)->call('assignContractor');
    expect($project->fresh()->main_contractor_id)->toBeNull();
    $screen()->set('contractorId', $active->id)->call('assignContractor');
    expect($project->fresh()->main_contractor_id)->toBe($active->id);
});
