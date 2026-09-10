<?php

declare(strict_types=1);

namespace Modules\Transport\Providers;

use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Modules\Transport\Models\Driver;
use Modules\Transport\Models\FuelLog;
use Modules\Transport\Models\LearnerTransport;
use Modules\Transport\Models\Route;
use Modules\Transport\Models\RouteStop;
use Modules\Transport\Models\TransportZone;
use Modules\Transport\Models\Trip;
use Modules\Transport\Models\TripPassenger;
use Modules\Transport\Models\Vehicle;
use Modules\Transport\Models\VehicleCompliance;
use Modules\Transport\Models\VehicleIncident;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book H2 Domain F: Operations & Estates — `OPS-01` Transport & Fleet
 * Management, built after `OPS-02` per the book's own order (vehicles
 * reference `OPS-02`'s `maintenance_assets`/`work_orders` for real).
 *
 * Real cross-module wiring in this pass: `vehicles.fixed_asset_id`
 * (`FIN-10`) and `vehicles.maintenance_asset_id`/
 * `vehicle_compliance.renewal_wo_id`/`vehicle_incidents.work_order_id`
 * (`OPS-02`, all real FKs); `fuel_logs.store_requisition_id` — a
 * genuine `FIN-09` requisition for a school-tank draw
 * (`RecordFuelLogAction`); `RecordTripOdometerAction` calls `OPS-02`'s
 * new `CheckUsageBasedMaintenanceAction` directly on every return
 * reading; `ReportVehicleIncidentAction` opens a real `BRD-06`
 * `HealthIncident` per learner involved through `Modules\Welfare`'s
 * own `RecordHealthIncidentAction`.
 *
 * Two deliberate, documented boundaries — both because the target
 * side is missing the mapping this module would need, not because the
 * wiring was skipped for convenience:
 *  - `FIN-02`'s `usage_based` billing basis doesn't exist yet
 *    (`Modules\Finance\Domain\Exceptions\UnsupportedBillingBasisException`
 *    already anticipates it by name) — `LearnerAssignedToRoute` fires
 *    for real, nothing yet subscribes to it, and zone-change proration
 *    stays administrative-only until that basis is built.
 *  - `FIN-10`'s `PreviewDepreciationRunAction` (Book H1, already
 *    shipped and gated) always calls `DepreciationCalculator::
 *    monthlyCharge()` with its default `$unitsConsumedThisPeriod =
 *    0.0` — `OdometerRecorded` fires for real, nothing yet subscribes
 *    to it either. Editing an already-gated earlier book's core
 *    action for this module's convenience is out of scope here, the
 *    same boundary `Modules\Stores`' own FIN-08/FIN-09 → FIN-10
 *    capitalisation listeners document.
 *  - `BR-OPS-01-015`/`016` (trips created from `BRD-06` medical
 *    referrals and `OPS-07` sports fixtures, with fixture-trip roll
 *    status) are skipped entirely: `OPS-07` doesn't exist yet in this
 *    codebase, and building only the `BRD-06` half would leave an
 *    inconsistent, asymmetric integration.
 */
class TransportServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Transport';

    protected string $nameLower = 'transport';

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
    }

    /**
     * Book H2 OPS-01 §5.
     */
    private function registerNotificationKeys(): void
    {
        $keys = [
            ['transport.compliance_expiring', ['compliance.compliance_type', 'days_remaining'], false],
            ['transport.vehicle_grounded', ['vehicle.fleet_number', 'reason'], true],
            ['transport.driver_document_expired', ['driver.staff_id', 'document_type'], true],
            ['transport.learner_boarded', ['student.first_name', 'student.last_name'], false],
            ['transport.learner_alighted', ['student.first_name', 'student.last_name'], false],
            ['transport.learner_no_show', ['student.first_name', 'student.last_name'], true],
            ['transport.fuel_anomaly_detected', ['vehicle.fleet_number'], true],
            ['transport.vehicle_incident_guardian_notified', ['student.first_name', 'incident_type'], true],
        ];

        foreach ($keys as [$key, $variables, $isUrgent]) {
            NotificationKeyRegistry::register(new NotificationKeyDefinition(
                key: $key,
                variables: $variables,
                defaultChannels: ['email', 'sms'],
                defaultAudience: 'guardian',
                isUrgent: $isUrgent,
                isTransactional: true,
            ));
        }
    }

    /**
     * Book H2 OPS-01 §5.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['transport.compliance_alert_days', 'json', '[60,30,7]', 'Days before a vehicle compliance document expires at which an alert fires.'],
            ['transport.block_trip_on_expired_compliance', 'bool', '1', 'Whether scheduling a trip is refused outright when any vehicle compliance has expired (locked true).'],
            ['transport.fuel_variance_tolerance_percent', 'int', '20', 'Fuel efficiency variance tolerance, individual fill and 30-day rolling.'],
            ['transport.fuel_rolling_window_days', 'int', '30', 'Rolling window for cumulative fuel consumption anomaly detection.'],
            ['transport.min_refuel_hours', 'int', '4', 'Minimum hours between two fuellings of the same vehicle before it is flagged.'],
            ['transport.notify_guardian_on_board', 'bool', '1', 'Whether a guardian is notified when their learner boards.'],
            ['transport.notify_guardian_on_alight', 'bool', '1', 'Whether a guardian is notified when their learner alights.'],
            ['transport.enforce_route_capacity', 'bool', '1', 'Whether route capacity is enforced (an override reason is still always required over capacity).'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'OPS',
                'group_key' => explode('.', $key)[0],
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => $dataType === 'bool' ? 'toggle' : 'text',
                'lowest_scope' => 'school',
                'is_encrypted' => false,
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * Book A Part 1.11's tenancy isolation test generator.
     */
    private function registerTenantModels(): void
    {
        TenantModelRegistry::register(Vehicle::class, fn (School $school): Vehicle => Vehicle::factory()->for($school)->create());

        TenantModelRegistry::register(VehicleCompliance::class, fn (School $school): VehicleCompliance => VehicleCompliance::factory()->for($school)->create());

        TenantModelRegistry::register(Driver::class, fn (School $school): Driver => Driver::factory()->for($school)->create());

        TenantModelRegistry::register(TransportZone::class, fn (School $school): TransportZone => TransportZone::factory()->for($school)->create());

        TenantModelRegistry::register(Route::class, fn (School $school): Route => Route::factory()->for($school)->create());

        TenantModelRegistry::register(RouteStop::class, function (School $school): RouteStop {
            $route = Route::factory()->for($school)->create();

            return RouteStop::factory()->create(['school_id' => $school->id, 'route_id' => $route->id]);
        });

        TenantModelRegistry::register(LearnerTransport::class, fn (School $school): LearnerTransport => LearnerTransport::factory()->for($school)->create());

        TenantModelRegistry::register(Trip::class, fn (School $school): Trip => Trip::factory()->for($school)->create());

        TenantModelRegistry::register(TripPassenger::class, function (School $school): TripPassenger {
            $trip = Trip::factory()->for($school)->create();

            return TripPassenger::factory()->create(['school_id' => $school->id, 'trip_id' => $trip->id]);
        });

        TenantModelRegistry::register(FuelLog::class, fn (School $school): FuelLog => FuelLog::factory()->for($school)->create());

        TenantModelRegistry::register(VehicleIncident::class, fn (School $school): VehicleIncident => VehicleIncident::factory()->for($school)->create());
    }
}
