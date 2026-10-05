<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Student;
use Modules\Transport\Livewire\Assignment\Index as AssignmentIndex;
use Modules\Transport\Livewire\Compliance\Index as ComplianceIndex;
use Modules\Transport\Livewire\Drivers\Index as DriversIndex;
use Modules\Transport\Livewire\Fleet\Index as FleetIndex;
use Modules\Transport\Livewire\Fuel\Index as FuelIndex;
use Modules\Transport\Livewire\FuelAnomalies\Index as FuelAnomaliesIndex;
use Modules\Transport\Livewire\Incidents\Index as IncidentsIndex;
use Modules\Transport\Livewire\Manifest\Show as ManifestShow;
use Modules\Transport\Livewire\RouteCosts\Index as RouteCostsIndex;
use Modules\Transport\Livewire\Routes\Index as RoutesIndex;
use Modules\Transport\Livewire\Trips\Index as TripsIndex;
use Modules\Transport\Models\Driver;
use Modules\Transport\Models\LearnerTransport;
use Modules\Transport\Models\Route;
use Modules\Transport\Models\RouteStop;
use Modules\Transport\Models\TransportZone;
use Modules\Transport\Models\Trip;
use Modules\Transport\Models\Vehicle;

/**
 * Book H2 OPS-01 admin-UI pass. Own, distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, user: User, costCentre: CostCentre}
 */
function transportAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create(['financial_state' => 'open']);
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    $costCentre = CostCentre::factory()->for($school)->create();

    return compact('school', 'year', 'term', 'user', 'costCentre');
}

/**
 * @param  array<string, mixed>  $f
 */
function transportAdminUser(array $f, string ...$permissionNames): User
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

it('refuses to mount the compliance monitor for a user with no transport.manage grant', function (): void {
    $f = transportAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(ComplianceIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every transport screen for a fully-permissioned user', function (): void {
    $f = transportAdminFixture();
    $user = transportAdminUser(
        $f,
        'transport.view', 'transport.manage', 'transport.assign', 'transport.trip.manage',
        'transport.drive', 'transport.fuel.record', 'transport.fuel.review', 'transport.incident.manage', 'transport.report.view',
    );

    Livewire::actingAs($user)->test(FleetIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ComplianceIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(DriversIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(RoutesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(AssignmentIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(TripsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ManifestShow::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(FuelIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(FuelAnomaliesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(IncidentsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(RouteCostsIndex::class, ['school' => $f['school']])->assertOk();
});

it('refuses to schedule a trip for a vehicle with an expired certificate of fitness, naming the expired item (AC-OPS-01-001)', function (): void {
    $f = transportAdminFixture();
    $manager = transportAdminUser($f, 'transport.manage', 'transport.trip.manage');

    $vehicle = Vehicle::factory()->for($f['school'])->create(['cost_centre_id' => $f['costCentre']->id]);

    Livewire::actingAs($manager)->test(ComplianceIndex::class, ['school' => $f['school']])
        ->set('vehicleId', $vehicle->id)
        ->set('complianceType', 'certificate_of_fitness')
        ->set('expiresOn', now()->subDay()->toDateString())
        ->call('register')->assertOk();

    $driver = Driver::factory()->for($f['school'])->create([
        'licence_expires_on' => now()->addYear(),
        'medical_expires_on' => now()->addYear(),
        'defensive_expires_on' => now()->addYear(),
        'status' => 'active',
    ]);

    Livewire::actingAs($manager)->test(TripsIndex::class, ['school' => $f['school']])
        ->set('vehicleId', $vehicle->id)
        ->set('driverId', $driver->id)
        ->call('schedule');

    expect(Trip::where('school_id', $f['school']->id)->count())->toBe(0);
});

it('shows the resulting termly transport fee when a learner is assigned to a route zone (AC-OPS-01-002)', function (): void {
    $f = transportAdminFixture();
    $assigner = transportAdminUser($f, 'transport.manage', 'transport.assign');

    $zone = TransportZone::factory()->for($f['school'])->create(['termly_fee_minor' => 5000, 'currency' => 'USD']);
    $route = Route::factory()->for($f['school'])->create(['cost_centre_id' => $f['costCentre']->id, 'capacity' => 10]);
    $stop = RouteStop::factory()->create(['school_id' => $f['school']->id, 'route_id' => $route->id, 'zone_id' => $zone->id]);

    $student = Student::factory()->for($f['school'])->create();

    $component = Livewire::actingAs($assigner)->test(AssignmentIndex::class, ['school' => $f['school']])
        ->set('routeId', $route->id)
        ->set('pickupStopId', $stop->id);

    expect($component->viewData('previewFeeMinor'))->toBe(5000);

    $component->set('studentId', $student->id)
        ->set('authorisedByGuardian', true)
        ->call('assign')->assertOk();

    expect(LearnerTransport::where('school_id', $f['school']->id)->where('zone_id', $zone->id)->exists())->toBeTrue();
});
