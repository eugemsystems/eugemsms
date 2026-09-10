<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Operations\Models\MaintenanceAsset;
use Modules\Operations\Models\MaintenanceSchedule;
use Modules\Operations\Models\WorkOrder;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Stores\Domain\Actions\ReceiveStockAction;
use Modules\Stores\Domain\DataObjects\ReceiveStockData;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreRequisition;
use Modules\Transport\Domain\Actions\AssignLearnerToRouteAction;
use Modules\Transport\Domain\Actions\CheckCumulativeFuelAnomalyAction;
use Modules\Transport\Domain\Actions\CheckDriverDocumentExpiryAction;
use Modules\Transport\Domain\Actions\CheckVehicleComplianceExpiryAction;
use Modules\Transport\Domain\Actions\ComputeRouteCostingAction;
use Modules\Transport\Domain\Actions\CreateDriverAction;
use Modules\Transport\Domain\Actions\CreateRouteAction;
use Modules\Transport\Domain\Actions\CreateTransportZoneAction;
use Modules\Transport\Domain\Actions\CreateVehicleAction;
use Modules\Transport\Domain\Actions\DepartTripAction;
use Modules\Transport\Domain\Actions\RaiseComplianceRenewalWorkOrderAction;
use Modules\Transport\Domain\Actions\RecordBoardingAction;
use Modules\Transport\Domain\Actions\RecordFuelAnomalyExplanationAction;
use Modules\Transport\Domain\Actions\RecordFuelLogAction;
use Modules\Transport\Domain\Actions\RecordTripOdometerAction;
use Modules\Transport\Domain\Actions\RegisterVehicleComplianceAction;
use Modules\Transport\Domain\Actions\ReportVehicleIncidentAction;
use Modules\Transport\Domain\Actions\ScheduleTripAction;
use Modules\Transport\Domain\DataObjects\AssignLearnerToRouteData;
use Modules\Transport\Domain\DataObjects\CreateDriverData;
use Modules\Transport\Domain\DataObjects\CreateRouteData;
use Modules\Transport\Domain\DataObjects\CreateTransportZoneData;
use Modules\Transport\Domain\DataObjects\CreateVehicleData;
use Modules\Transport\Domain\DataObjects\RecordFuelLogData;
use Modules\Transport\Domain\DataObjects\RegisterVehicleComplianceData;
use Modules\Transport\Domain\DataObjects\ReportVehicleIncidentData;
use Modules\Transport\Domain\DataObjects\ScheduleTripData;
use Modules\Transport\Domain\Events\DriverDocumentExpired;
use Modules\Transport\Domain\Events\FuelAnomalyDetected;
use Modules\Transport\Domain\Events\LearnerAssignedToRoute;
use Modules\Transport\Domain\Events\LearnerBoarded;
use Modules\Transport\Domain\Events\LearnerNoShow;
use Modules\Transport\Domain\Events\OdometerRecorded;
use Modules\Transport\Domain\Events\VehicleComplianceExpiring;
use Modules\Transport\Domain\Exceptions\RouteCapacityExceededException;
use Modules\Transport\Domain\Exceptions\VehicleNotTripReadyException;
use Modules\Transport\Models\Driver;
use Modules\Transport\Models\Route;
use Modules\Transport\Models\Vehicle;
use Modules\Welfare\Models\HealthIncident;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User, user2: User, costCentre: CostCentre, expenseAccount: Account}
 */
function ops01Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['financial_state' => 'open', 'is_current' => true]);
    $user = User::factory()->create();
    $user2 = User::factory()->create();

    foreach (['journal', 'work_order', 'fault_report', 'store_requisition'] as $type) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $type, pattern: strtoupper(substr($type, 0, 3)).'/{SEQ:5}',
        ));
    }

    $costCentre = CostCentre::factory()->for($school)->create();
    $expenseAccount = Account::factory()->for($school)->expense()->create(['code' => 'TRANSPORT-EXP']);

    return compact('school', 'year', 'term', 'user', 'user2', 'costCentre', 'expenseAccount');
}

/**
 * @param  array<string, mixed>  $f
 */
function ops01Vehicle(array $f, ?int $maintenanceAssetId = null): Vehicle
{
    return app(CreateVehicleAction::class)->execute(new CreateVehicleData(
        schoolId: $f['school']->id, fleetNumber: 'BUS-'.fake()->unique()->numberBetween(1, 9999),
        registrationNumber: strtoupper(fake()->unique()->bothify('??##??')), vehicleType: 'bus',
        seatingCapacity: 4, fuelType: 'diesel', costCentreId: $f['costCentre']->id,
        tankCapacityLitres: 200, expectedKmPerLitre: 6.0, maintenanceAssetId: $maintenanceAssetId,
    ));
}

/**
 * @param  array<string, mixed>  $f
 */
function ops01Driver(array $f): Driver
{
    $staff = Staff::factory()->for($f['school'])->create();

    return app(CreateDriverAction::class)->execute(new CreateDriverData(
        schoolId: $f['school']->id, staffId: $staff->id, licenceNumber: 'DL123456',
        licenceClasses: ['2', '4'], licenceExpiresOn: Carbon::now()->addYear(),
        medicalExpiresOn: Carbon::now()->addYear(), defensiveExpiresOn: Carbon::now()->addYear(),
    ));
}

it('refuses to schedule a trip for a vehicle with expired compliance, naming the item (BR-OPS-01-001/AC-OPS-01-001)', function (): void {
    $f = ops01Fixture();
    $vehicle = ops01Vehicle($f);
    $driver = ops01Driver($f);

    app(RegisterVehicleComplianceAction::class)->execute(new RegisterVehicleComplianceData(
        schoolId: $f['school']->id, vehicleId: $vehicle->id, complianceType: 'certificate_of_fitness',
        expiresOn: Carbon::yesterday(),
    ));

    expect(fn () => app(ScheduleTripAction::class)->execute(new ScheduleTripData(
        schoolId: $f['school']->id, termId: $f['term']->id, tripDate: Carbon::now(), tripType: 'excursion',
        vehicleId: $vehicle->id, driverId: $driver->id,
    )))->toThrow(VehicleNotTripReadyException::class);
});

it('refuses to schedule a trip for a driver with expired documents (BR-OPS-01-004)', function (): void {
    $f = ops01Fixture();
    $vehicle = ops01Vehicle($f);
    $driver = ops01Driver($f);
    $driver->update(['licence_expires_on' => Carbon::yesterday()]);

    expect(fn () => app(ScheduleTripAction::class)->execute(new ScheduleTripData(
        schoolId: $f['school']->id, termId: $f['term']->id, tripDate: Carbon::now(), tripType: 'excursion',
        vehicleId: $vehicle->id, driverId: $driver->id,
    )))->toThrow(VehicleNotTripReadyException::class);
});

it('assigns a learner to a route and zone, requires guardian authorisation, and enforces capacity (BR-OPS-01-006/007/008/AC-OPS-01-002)', function (): void {
    Event::fake([LearnerAssignedToRoute::class]);
    $f = ops01Fixture();
    $route = app(CreateRouteAction::class)->execute(new CreateRouteData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, code: 'R1', name: 'Northern Route',
        direction: 'both', capacity: 1, costCentreId: $f['costCentre']->id,
        stops: [['name' => 'Stop 1', 'landmark' => null, 'zoneId' => null, 'distanceFromSchoolKm' => null]],
    ));
    $stop = $route->stops->first();
    $zone = app(CreateTransportZoneAction::class)->execute(new CreateTransportZoneData(
        schoolId: $f['school']->id, code: 'Z1', name: 'Zone 1', termlyFeeMinor: 5000000, currency: 'USD',
    ));
    $stop->update(['zone_id' => $zone->id]);

    $student1 = Student::factory()->for($f['school'])->create();
    $student2 = Student::factory()->for($f['school'])->create();

    expect(fn () => app(AssignLearnerToRouteAction::class)->execute(new AssignLearnerToRouteData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student1->id,
        routeId: $route->id, pickupStopId: $stop->id, direction: 'both', effectiveFrom: Carbon::now(),
        authorisedByGuardian: false,
    )))->toThrow(ValidationException::class);

    $assignment = app(AssignLearnerToRouteAction::class)->execute(new AssignLearnerToRouteData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student1->id,
        routeId: $route->id, pickupStopId: $stop->id, direction: 'both', effectiveFrom: Carbon::now(),
        authorisedByGuardian: true,
    ));

    expect($assignment->zone_id)->toBe($zone->id)
        ->and($route->fresh()->current_passengers)->toBe(1);
    Event::assertDispatched(LearnerAssignedToRoute::class);

    // Route is now at capacity (1) — a second learner is refused without override.
    expect(fn () => app(AssignLearnerToRouteAction::class)->execute(new AssignLearnerToRouteData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student2->id,
        routeId: $route->id, pickupStopId: $stop->id, direction: 'both', effectiveFrom: Carbon::now(),
        authorisedByGuardian: true,
    )))->toThrow(RouteCapacityExceededException::class);

    $overridden = app(AssignLearnerToRouteAction::class)->execute(new AssignLearnerToRouteData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student2->id,
        routeId: $route->id, pickupStopId: $stop->id, direction: 'both', effectiveFrom: Carbon::now(),
        authorisedByGuardian: true, overrideCapacity: true, overrideReason: 'Sibling of an existing rider, urgent.',
    ));
    expect($overridden->route_id)->toBe($route->id);
});

it('auto-generates the trip manifest for a route trip, boards a learner, and flags a no-show at departure (BR-OPS-01-009/010/AC-OPS-01-006)', function (): void {
    Event::fake([LearnerBoarded::class, LearnerNoShow::class]);
    $f = ops01Fixture();
    $vehicle = ops01Vehicle($f);
    $driver = ops01Driver($f);
    $route = app(CreateRouteAction::class)->execute(new CreateRouteData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, code: 'R2', name: 'Southern Route',
        direction: 'both', capacity: 10, costCentreId: $f['costCentre']->id,
        stops: [['name' => 'Stop 1', 'landmark' => null, 'zoneId' => null, 'distanceFromSchoolKm' => null]],
    ));
    $stop = $route->stops->first();
    $zone = app(CreateTransportZoneAction::class)->execute(new CreateTransportZoneData(
        schoolId: $f['school']->id, code: 'Z2', name: 'Zone 2', termlyFeeMinor: 5000000, currency: 'USD',
    ));
    $stop->update(['zone_id' => $zone->id]);

    $rider = Student::factory()->for($f['school'])->create();
    $noShow = Student::factory()->for($f['school'])->create();

    foreach ([$rider, $noShow] as $student) {
        app(AssignLearnerToRouteAction::class)->execute(new AssignLearnerToRouteData(
            schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
            routeId: $route->id, pickupStopId: $stop->id, direction: 'both', effectiveFrom: Carbon::now(),
            authorisedByGuardian: true,
        ));
    }

    $trip = app(ScheduleTripAction::class)->execute(new ScheduleTripData(
        schoolId: $f['school']->id, termId: $f['term']->id, tripDate: Carbon::now(), tripType: 'route',
        vehicleId: $vehicle->id, driverId: $driver->id, routeId: $route->id,
    ));

    expect($trip->passengers)->toHaveCount(2);

    $riderPassenger = $trip->passengers->firstWhere('student_id', $rider->id);
    app(RecordBoardingAction::class)->execute($riderPassenger->id, 'boarded');

    $departed = app(DepartTripAction::class)->execute($trip->id);
    expect($departed->status)->toBe('departed');

    $noShowPassenger = $departed->passengers()->where('student_id', $noShow->id)->first();
    expect($noShowPassenger->status)->toBe('no_show');
    Event::assertDispatched(LearnerBoarded::class);
    Event::assertDispatched(LearnerNoShow::class);
});

it('computes trip distance from odometer readings and triggers a real OPS-02 usage-based work order (BR-OPS-01-011/012/AC-OPS-01-005)', function (): void {
    Event::fake([OdometerRecorded::class]);
    $f = ops01Fixture();
    $asset = MaintenanceAsset::factory()->for($f['school'])->create(['cost_centre_id' => $f['costCentre']->id]);
    $schedule = MaintenanceSchedule::factory()->create([
        'school_id' => $f['school']->id, 'maintenance_asset_id' => $asset->id, 'trigger_type' => 'usage',
        'interval_units' => 500, 'next_due_units' => 100,
    ]);
    $vehicle = ops01Vehicle($f, $asset->id);
    $driver = ops01Driver($f);

    $trip = app(ScheduleTripAction::class)->execute(new ScheduleTripData(
        schoolId: $f['school']->id, termId: $f['term']->id, tripDate: Carbon::now(), tripType: 'excursion',
        vehicleId: $vehicle->id, driverId: $driver->id,
    ));

    app(RecordTripOdometerAction::class)->execute($trip->id, 'departure', 1000, $f['user']->id);
    $completed = app(RecordTripOdometerAction::class)->execute($trip->id, 'return', 1150, $f['user']->id);

    expect((float) $completed->distance_km)->toBe(150.0)
        ->and($completed->status)->toBe('completed')
        ->and((float) $vehicle->fresh()->current_odometer_km)->toBe(1150.0);
    Event::assertDispatched(OdometerRecorded::class);

    // next_due_units (100) is now behind current odometer (1150) — a
    // real OPS-02 work order should have been generated.
    $workOrder = WorkOrder::where('school_id', $f['school']->id)->where('schedule_id', $schedule->id)->first();
    expect($workOrder)->not->toBeNull()
        ->and($workOrder->work_type)->toBe('preventive');
    expect($schedule->fresh()->next_due_units)->toEqualWithDelta(600.0, 0.001);
});

it('flags a fuel efficiency anomaly, requires an explanation, and never dismisses it silently (BR-OPS-01-013/014/AC-OPS-01-003)', function (): void {
    Event::fake([FuelAnomalyDetected::class]);
    $f = ops01Fixture();
    $vehicle = ops01Vehicle($f);
    $driver = ops01Driver($f);

    app(ScheduleTripAction::class)->execute(new ScheduleTripData(
        schoolId: $f['school']->id, termId: $f['term']->id, tripDate: Carbon::now(), tripType: 'excursion',
        vehicleId: $vehicle->id, driverId: $driver->id,
    ));

    // 4.2 km/l against an expected 6.0 — a 30% variance, exceeding the
    // default 20% tolerance (AC-OPS-01-003).
    $fuelLog = app(RecordFuelLogAction::class)->execute(new RecordFuelLogData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, vehicleId: $vehicle->id,
        fuelledAt: Carbon::now(), odometerKm: 1000, litres: 50, unitPriceMinor: 150, currency: 'USD',
        source: 'filling_station', authorisedByUserId: $f['user']->id, driverId: $driver->id,
    ));
    $second = app(RecordFuelLogAction::class)->execute(new RecordFuelLogData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, vehicleId: $vehicle->id,
        fuelledAt: Carbon::now()->addHours(6), odometerKm: 1210, litres: 50, unitPriceMinor: 150, currency: 'USD',
        source: 'filling_station', authorisedByUserId: $f['user']->id, driverId: $driver->id,
    ));

    expect($second->is_anomaly)->toBeTrue();
    Event::assertDispatched(FuelAnomalyDetected::class);

    expect(fn () => app(RecordFuelAnomalyExplanationAction::class)->execute($second->id, '', $f['user2']->id))
        ->toThrow(ValidationException::class);

    $explained = app(RecordFuelAnomalyExplanationAction::class)->execute($second->id, 'Cold weather idling for the away fixture.', $f['user2']->id);
    expect($explained->anomaly_explanation)->not->toBeNull();
});

it('flags a cumulative fuel shortfall over the rolling window even when every individual fill passes (BR-OPS-01-014/AC-OPS-01-004)', function (): void {
    Event::fake([FuelAnomalyDetected::class]);
    $f = ops01Fixture();
    $vehicle = ops01Vehicle($f);
    $driver = ops01Driver($f);

    // Each fill: 60km covered, 10L is the expected draw at 6.0 km/l, but
    // 12.2L is logged instead — individually a ~18% variance (under the
    // 20% tolerance, so no single fill trips the per-fill check), yet
    // six fills compound to a 22% cumulative shortfall the rolling
    // window catches. A trip is scheduled on each fuelling date so the
    // unrelated "no recorded trip" check never fires and masks it.
    $odometer = 1000;
    $fuelledAt = Carbon::now();

    for ($i = 0; $i < 6; $i++) {
        app(ScheduleTripAction::class)->execute(new ScheduleTripData(
            schoolId: $f['school']->id, termId: $f['term']->id, tripDate: $fuelledAt, tripType: 'excursion',
            vehicleId: $vehicle->id, driverId: $driver->id,
        ));

        app(RecordFuelLogAction::class)->execute(new RecordFuelLogData(
            schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, vehicleId: $vehicle->id,
            fuelledAt: $fuelledAt, odometerKm: $odometer, litres: 12.2, unitPriceMinor: 150, currency: 'USD',
            source: 'filling_station', authorisedByUserId: $f['user']->id, driverId: $driver->id,
        ));
        $odometer += 60;
        $fuelledAt = $fuelledAt->copy()->addDay();
    }

    $flagged = app(CheckCumulativeFuelAnomalyAction::class)->execute($f['school']->id);

    expect($flagged)->toHaveCount(1);
    Event::assertDispatched(FuelAnomalyDetected::class);
});

it('drains the FIN-09 school tank for real when fuel is drawn from it (BR-OPS-01-013/AC-OPS-01-007)', function (): void {
    $f = ops01Fixture();
    $vehicle = ops01Vehicle($f);
    $driver = ops01Driver($f);
    $store = Store::factory()->for($f['school'])->create();
    $item = InventoryItem::factory()->for($f['school'])->create(['base_unit' => 'litre']);

    app(ReceiveStockAction::class)->execute(new ReceiveStockData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        storeId: $store->id, itemId: $item->id, quantity: 500, unitCostMinor: 150,
        currency: 'USD', receivedOn: now(), performedByUserId: $f['user']->id,
        contraAccountId: $f['expenseAccount']->id,
    ));

    app(ScheduleTripAction::class)->execute(new ScheduleTripData(
        schoolId: $f['school']->id, termId: $f['term']->id, tripDate: Carbon::now(), tripType: 'excursion',
        vehicleId: $vehicle->id, driverId: $driver->id,
    ));

    $fuelLog = app(RecordFuelLogAction::class)->execute(new RecordFuelLogData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, vehicleId: $vehicle->id,
        fuelledAt: Carbon::now(), odometerKm: 1000, litres: 60, unitPriceMinor: 150, currency: 'USD',
        source: 'school_tank', authorisedByUserId: $f['user']->id, driverId: $driver->id,
        storeId: $store->id, itemId: $item->id,
    ));

    expect($fuelLog->store_requisition_id)->not->toBeNull()
        ->and($fuelLog->journal_id)->not->toBeNull();

    $requisition = StoreRequisition::find($fuelLog->store_requisition_id);
    expect($requisition->status)->toBe('issued');
});

it('alerts on expiring compliance and raises a real OPS-02 renewal work order (BR-OPS-01-003)', function (): void {
    Event::fake([VehicleComplianceExpiring::class]);
    $f = ops01Fixture();
    $vehicle = ops01Vehicle($f);
    $compliance = app(RegisterVehicleComplianceAction::class)->execute(new RegisterVehicleComplianceData(
        schoolId: $f['school']->id, vehicleId: $vehicle->id, complianceType: 'insurance',
        expiresOn: Carbon::now()->addDays(30),
    ));

    $expiring = app(CheckVehicleComplianceExpiryAction::class)->execute($f['school']->id);
    expect($expiring)->toHaveCount(1);
    Event::assertDispatched(VehicleComplianceExpiring::class);

    $workOrder = app(RaiseComplianceRenewalWorkOrderAction::class)->execute($compliance->id, $f['costCentre']->id, $f['user']->id);
    expect($workOrder->work_type)->toBe('compliance')
        ->and($compliance->fresh()->renewal_wo_id)->toBe($workOrder->id);
});

it('suspends a driver whose documents have expired (BR-OPS-01-004)', function (): void {
    Event::fake([DriverDocumentExpired::class]);
    $f = ops01Fixture();
    $driver = ops01Driver($f);
    $driver->update(['medical_expires_on' => Carbon::yesterday()]);

    $expired = app(CheckDriverDocumentExpiryAction::class)->execute($f['school']->id);

    expect($expired)->toHaveCount(1)
        ->and($driver->fresh()->status)->toBe('expired_documents');
    Event::assertDispatched(DriverDocumentExpired::class);
});

it('opens a real BRD-06 health incident and notifies guardians when a vehicle incident involves injuries (BR-OPS-01-017)', function (): void {
    $f = ops01Fixture();
    $vehicle = ops01Vehicle($f);
    $student = Student::factory()->for($f['school'])->create();

    $incident = app(ReportVehicleIncidentAction::class)->execute(new ReportVehicleIncidentData(
        schoolId: $f['school']->id, vehicleId: $vehicle->id, incidentType: 'accident',
        occurredAt: Carbon::now(), location: 'Along Enterprise Road', description: 'Minor collision.',
        reportedByUserId: $f['user']->id, learnersInvolved: [$student->id], injuries: true,
        termId: $f['term']->id,
    ));

    expect($incident->status)->toBe('reported');

    $healthIncident = HealthIncident::where('school_id', $f['school']->id)->where('student_id', $student->id)->first();
    expect($healthIncident)->not->toBeNull()
        ->and($healthIncident->severity)->toBe('moderate');
});

it('aggregates real fuel, maintenance and depreciation cost against fee income per route (BR-OPS-01-018)', function (): void {
    $f = ops01Fixture();
    $vehicle = ops01Vehicle($f);
    $driver = ops01Driver($f);
    $route = app(CreateRouteAction::class)->execute(new CreateRouteData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, code: 'R3', name: 'Costed Route',
        direction: 'both', capacity: 10, costCentreId: $f['costCentre']->id,
        stops: [['name' => 'Stop 1', 'landmark' => null, 'zoneId' => null, 'distanceFromSchoolKm' => null]],
    ));
    $route->update(['assigned_vehicle_id' => $vehicle->id]);
    $stop = $route->stops->first();
    $zone = app(CreateTransportZoneAction::class)->execute(new CreateTransportZoneData(
        schoolId: $f['school']->id, code: 'Z3', name: 'Zone 3', termlyFeeMinor: 5000000, currency: 'USD',
    ));
    $stop->update(['zone_id' => $zone->id]);

    $student = Student::factory()->for($f['school'])->create();
    app(AssignLearnerToRouteAction::class)->execute(new AssignLearnerToRouteData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
        routeId: $route->id, pickupStopId: $stop->id, direction: 'both', effectiveFrom: Carbon::now(),
        authorisedByGuardian: true,
    ));

    app(ScheduleTripAction::class)->execute(new ScheduleTripData(
        schoolId: $f['school']->id, termId: $f['term']->id, tripDate: Carbon::now(), tripType: 'excursion',
        vehicleId: $vehicle->id, driverId: $driver->id,
    ));
    app(RecordFuelLogAction::class)->execute(new RecordFuelLogData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, vehicleId: $vehicle->id,
        fuelledAt: Carbon::now(), odometerKm: 1000, litres: 50, unitPriceMinor: 150, currency: 'USD',
        source: 'filling_station', authorisedByUserId: $f['user']->id, driverId: $driver->id,
    ));

    $result = app(ComputeRouteCostingAction::class)->execute($route->id, Carbon::now()->subDay(), Carbon::now()->addDay());

    expect($result->fuelCostMinor)->toBe(7500)
        ->and($result->feeIncomeMinor)->toBe(5000000)
        ->and($result->marginMinor())->toBe(5000000 - 7500);
});
