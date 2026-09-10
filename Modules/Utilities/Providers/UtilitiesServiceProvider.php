<?php

declare(strict_types=1);

namespace Modules\Utilities\Providers;

use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Modules\Utilities\Models\Generator;
use Modules\Utilities\Models\GeneratorRun;
use Modules\Utilities\Models\LoadSheddingSchedule;
use Modules\Utilities\Models\Meter;
use Modules\Utilities\Models\MeterReading;
use Modules\Utilities\Models\PrepaidTokenPurchase;
use Modules\Utilities\Models\SolarGeneration;
use Modules\Utilities\Models\SolarInstallation;
use Modules\Utilities\Models\UtilityAccount;
use Modules\Utilities\Models\WaterReading;
use Modules\Utilities\Models\WaterSource;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book H2 Domain F: Operations & Estates — `OPS-04` Utilities &
 * Energy Management, built after `OPS-01` per the book's own order.
 *
 * Real cross-module wiring in this pass: `generators.fixed_asset_id`
 * (`FIN-10`) and `.maintenance_asset_id`/`water_sources.
 * maintenance_asset_id`/`solar_installations.maintenance_asset_id`
 * (`OPS-02`), all real FKs; `generator_runs.store_requisition_id` — a
 * genuine `FIN-09` requisition for a school-tank diesel draw
 * (`StopGeneratorRunAction`, mirroring `Modules\Transport`'s own fuel
 * action); `StopGeneratorRunAction` calls `OPS-02`'s
 * `CheckUsageBasedMaintenanceAction` directly on every stop; a
 * reduced-yield water reading raises a real `OPS-02` `WorkOrder`
 * (`RecordWaterReadingAction`); `ComputeOutageCostAction` reads real
 * `OPS-02` maintenance cost and real `FIN-10` depreciation for a
 * generator, the same aggregation `Modules\Transport\Domain\Actions\
 * ComputeRouteCostingAction` uses for a route. `RecordMeterReadingAction`
 * recognises real electricity consumption expense (Dr Expense / Cr
 * Prepaid Electricity) for a prepaid meter, never at token purchase
 * (BR-OPS-04-004) — `PurchasePrepaidTokenAction` posts only the
 * prepayment asset entry. `BR-OPS-04-018`'s "utility costs appear in
 * `FIN-11` budget variance" needs no new code at all: both journals
 * above post with a real `cost_centre_id`, and `FIN-11`'s own
 * `RecalculateBudgetLineActualsAction`/`CheckBudgetVarianceAction`
 * (Book H1, already shipped) already derive actuals from
 * `journal_lines` by account/cost-centre — there is nothing this
 * module needs to fire or subscribe to for that rule to hold.
 *
 * Two deliberate, documented boundaries:
 *  - `grid_cost` in `ComputeOutageCostAction`/`OutageCostResult` counts
 *    only credited prepaid token spend — postpaid utility invoices
 *    have no link back to `utility_accounts` in this schema (`FIN-08`'s
 *    `supplier_invoices` carries no generic source column, the same
 *    boundary `Modules\Transport\Domain\Actions\RecordContractorCostAction`
 *    already documents for a cost it can't complete alone).
 *  - The spec's own "estimated productivity impact" addend to outage
 *    cost is omitted entirely — there is no real source in this
 *    codebase to compute it from, and a fabricated number would be
 *    worse than an honest gap.
 */
class UtilitiesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Utilities';

    protected string $nameLower = 'utilities';

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
    }

    /**
     * Book H2 OPS-04 §5.
     */
    private function registerNotificationKeys(): void
    {
        $keys = [
            ['utilities.token_uncredited', ['purchase.token_number', 'hours_uncredited'], true],
            ['utilities.token_reconciliation_variance', ['meter.meter_number', 'variance_percent'], false],
            ['utilities.meter_reading_anomaly', ['reading.meter_id', 'reason'], false],
            ['utilities.generator_fuel_anomaly', ['generator.code'], true],
            ['utilities.water_storage_low', ['source.name'], true],
            ['utilities.borehole_yield_reduced', ['source.name', 'yield_percent'], true],
            ['utilities.water_quality_failed_nurse', ['source.name'], true],
            ['utilities.water_quality_failed_catering', ['source.name'], true],
        ];

        foreach ($keys as [$key, $variables, $isUrgent]) {
            NotificationKeyRegistry::register(new NotificationKeyDefinition(
                key: $key,
                variables: $variables,
                defaultChannels: ['email'],
                defaultAudience: 'staff',
                isUrgent: $isUrgent,
                isTransactional: true,
            ));
        }
    }

    /**
     * Book H2 OPS-04 §6.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['utilities.token_credit_window_hours', 'int', '24', 'Hours a prepaid token may sit uncredited before the estates manager and bursar are alerted (BR-OPS-04-002).'],
            ['utilities.token_reconciliation_tolerance_percent', 'int', '5', 'Variance tolerance between credited units and metered consumption before investigation is required.'],
            ['utilities.consumption_anomaly_tolerance_percent', 'int', '25', 'Variance tolerance for a meter reading against the rolling daily-average baseline.'],
            ['utilities.generator_load_factor', 'decimal', '0.60', 'Assumed load factor used to estimate kWh generated from generator hours run.'],
            ['utilities.generator_fuel_variance_percent', 'int', '20', 'Generator litres-per-hour variance tolerance against the baseline.'],
            ['utilities.water_storage_alert_percent', 'int', '30', 'Storage level, as a percentage, below which the estates manager and boarding master are alerted.'],
            ['utilities.borehole_yield_alert_percent', 'int', '70', 'Observed yield, as a percentage of baseline, below which a source is flagged reduced_yield and a work order raised.'],
            ['utilities.water_quality_test_days', 'int', '90', 'Days between water quality tests before one is considered overdue.'],
            ['utilities.boarding_master_staff_id', 'int', '', 'Staff record for the boarding master, notified when water storage runs low.'],
            ['utilities.catering_manager_staff_id', 'int', '', 'Staff record for the catering manager, notified when a water quality test returns not_potable.'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'OPS',
                'group_key' => explode('.', $key)[0],
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => 'text',
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
        TenantModelRegistry::register(UtilityAccount::class, fn (School $school): UtilityAccount => UtilityAccount::factory()->for($school)->create());

        TenantModelRegistry::register(Meter::class, fn (School $school): Meter => Meter::factory()->for($school)->create());

        TenantModelRegistry::register(PrepaidTokenPurchase::class, fn (School $school): PrepaidTokenPurchase => PrepaidTokenPurchase::factory()->for($school)->create());

        TenantModelRegistry::register(MeterReading::class, fn (School $school): MeterReading => MeterReading::factory()->for($school)->create());

        TenantModelRegistry::register(Generator::class, fn (School $school): Generator => Generator::factory()->for($school)->create());

        TenantModelRegistry::register(GeneratorRun::class, fn (School $school): GeneratorRun => GeneratorRun::factory()->for($school)->create());

        TenantModelRegistry::register(SolarInstallation::class, fn (School $school): SolarInstallation => SolarInstallation::factory()->for($school)->create());

        TenantModelRegistry::register(SolarGeneration::class, fn (School $school): SolarGeneration => SolarGeneration::factory()->for($school)->create());

        TenantModelRegistry::register(WaterSource::class, fn (School $school): WaterSource => WaterSource::factory()->for($school)->create());

        TenantModelRegistry::register(WaterReading::class, fn (School $school): WaterReading => WaterReading::factory()->for($school)->create());

        TenantModelRegistry::register(LoadSheddingSchedule::class, fn (School $school): LoadSheddingSchedule => LoadSheddingSchedule::factory()->for($school)->create());
    }
}
